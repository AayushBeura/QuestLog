const express = require("express")
const http = require("http")
const WebSocket = require("ws")
const QRCode = require("qrcode")
const crypto = require("crypto")
const os = require("os")

function getLocalIpAddress() {
    const interfaces = os.networkInterfaces();
    let bestIp = '127.0.0.1';
    for (const name of Object.keys(interfaces)) {
        for (const iface of interfaces[name]) {
            if (iface.family === 'IPv4' && !iface.internal) {
                if (iface.address.startsWith('192.168.1.')) return iface.address;
                if (!iface.address.startsWith('192.168.56.')) bestIp = iface.address;
            }
        }
    }
    return bestIp !== '127.0.0.1' ? bestIp : '192.168.1.6';
}

const app = express()
const server = http.createServer(app)
const wss = new WebSocket.Server({ server })

app.use(express.json())
app.use((req, res, next) => {
    res.setHeader("Access-Control-Allow-Origin", "*");
    res.setHeader("Access-Control-Allow-Headers", "Content-Type");
    res.setHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
    if (req.method === "OPTIONS") return res.sendStatus(200);
    next();
});

const sessions = {}

app.get("/api/qr", async (req, res) => {
    const sessionId = crypto.randomUUID();
    const amount = req.query.amount || Math.floor(Math.random() * 5000) + 500;
    const bookingId = req.query.bookingId || crypto.randomBytes(3).toString("hex").toUpperCase();
    
    sessions[sessionId] = {
        confirmed: false,
        ws: null,
        expiresAt: Date.now() + 5 * 60 * 1000,
        amount: amount,
        bookingId: bookingId
    };

    // Note: use local IP or localhost so phone on same network can reach it if needed. 
    // Use the dynamic local IP address instead of locahost so mobile devices can access it on the local network.
    const localIp = getLocalIpAddress();
    const url = `http://${localIp}:3000/pay/${sessionId}`;
    const qr = await QRCode.toDataURL(url);

    res.json({ sessionId, qr, amount, bookingId });
});

app.get("/", async (req, res) => {

    const sessionId = crypto.randomUUID()

    const amount = req.query.amount || Math.floor(Math.random() * 5000) + 500;
    const bookingId = req.query.bookingId || crypto.randomBytes(3).toString("hex").toUpperCase();
    
    sessions[sessionId] = {
        confirmed: false,
        ws: null,
        expiresAt: Date.now() + 5 * 60 * 1000,
        amount: amount,
        bookingId: bookingId
    }

    const url = `http://${req.get("host")}/pay/${sessionId}`

    const qr = await QRCode.toDataURL(url)

    res.send(`
        <html>
        <body style="font-family:sans-serif;text-align:center">
        <h2>Booking Payment</h2>
        <p>Booking ID: <strong>${bookingId}</strong></p>
        <p>Amount: <strong>₹${amount}</strong></p>
        <h2>Scan to Confirm Payment</h2>
        <img src="${qr}" width="250"/>
        <h3 id="timer">Time left: 05:00</h3>
        <h3 id="status">Waiting for confirmation...</h3>

        <script>
            let timeLeft = 300;
            const timerEl = document.getElementById("timer");
            const interval = setInterval(() => {
                timeLeft--;
                const m = String(Math.floor(timeLeft / 60)).padStart(2, '0');
                const s = String(timeLeft % 60).padStart(2, '0');
                timerEl.innerText = \`Time left: \${m}:\${s}\`;
                
                if (timeLeft <= 0) {
                    clearInterval(interval);
                    timerEl.innerText = "Payment Unsuccessful - Time Expired";
                    timerEl.style.color = "red";
                    document.getElementById("status").style.display = "none";
                }
            }, 1000);

            const ws = new WebSocket("ws://"+location.host)

            ws.onopen = () => {
                ws.send("${sessionId}")
            }

            ws.onmessage = (msg)=>{
                if(msg.data === "confirmed"){
                    clearInterval(interval);
                    timerEl.style.display = "none";
                    document.getElementById("status").innerText = "Payment Successful ✅";
                    document.getElementById("status").style.color = "green";
                } else if(msg.data === "expired"){
                    clearInterval(interval);
                    timerEl.innerText = "Payment Unsuccessful - Time Expired";
                    timerEl.style.color = "red";
                    document.getElementById("status").style.display = "none";
                }
            }
        </script>
        </body>
        </html>
    `)
})

app.get("/pay/:id", (req,res)=>{

    const id = req.params.id
    const session = sessions[id]

    if (!session) {
        return res.send("<h2 style='text-align:center; font-family:sans-serif;'>Invalid Payment Session</h2>");
    }
    
    if (Date.now() > session.expiresAt) {
        return res.send("<h2 style='text-align:center; font-family:sans-serif; color:red;'>Payment Link Expired</h2>");
    }

    if (session.confirmed) {
        return res.send("<h2 style='text-align:center; font-family:sans-serif; color:green;'>Payment Already Completed</h2>");
    }

    res.send(`
        <html>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <body style="font-family:sans-serif;text-align:center">
        <h2>Confirm Payment</h2>
        <div style="border:1px solid #ccc; padding: 20px; border-radius: 10px; display:inline-block; margin-bottom:20px;">
            <p><strong>Booking ID:</strong> ${session.bookingId}</p>
            <p><strong>Amount to Pay:</strong> ₹${session.amount}</p>
        </div>
        <br/>
        <button style="padding:10px 20px; font-size:16px; background-color:#28a745; color:white; border:none; border-radius:5px; cursor:pointer;" onclick="confirmPayment()">Pay Now</button>

        <script>
        function confirmPayment(){
            fetch("/confirm/${id}",{method:"POST"})
            .then(res => res.text())
            .then(data => {
                if(data === "expired") {
                    document.body.innerHTML="<h2 style='color:red;font-family:sans-serif;text-align:center;margin-top:50px;'>Payment Failed: Time Expired ❌</h2>";
                } else if(data === "ok") {
                    document.body.innerHTML="<h2 style='color:green;font-family:sans-serif;text-align:center;margin-top:50px;'>Payment Successful ✔</h2>";
                }
            });
        }
        </script>
        </body>
        </html>
    `)
})

app.post("/confirm/:id",(req,res)=>{

    const id = req.params.id
    const session = sessions[id]

    if (!session) return res.send("invalid")

    if (Date.now() > session.expiresAt) {
        if(session.ws) session.ws.send("expired")
        return res.send("expired")
    }

    session.confirmed = true

    if(session.ws){
        session.ws.send("confirmed")
    }

    res.send("ok")
})

wss.on("connection",(ws)=>{

    ws.on("message",(sessionId)=>{

        if(sessions[sessionId]){
            sessions[sessionId].ws = ws
        }
    })

})

server.listen(3000, '0.0.0.0', () => {
    console.log("Server running on 0.0.0.0:3000")
})
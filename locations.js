const worldLocations = [
    // Indian Cities
    "Mumbai (BOM)", "Delhi (DEL)", "Bangalore (BLR)", "Hyderabad (HYD)", "Chennai (MAA)",
    "Kolkata (CCU)", "Ahmedabad (AMD)", "Pune (PNQ)", "Jaipur (JAI)", "Surat (STV)",
    "Lucknow (LKO)", "Kanpur (KNU)", "Nagpur (NAG)", "Indore (IDR)", "Thane (TNA)",
    "Bhopal (BHO)", "Visakhapatnam (VTZ)", "Pimpri-Chinchwad (PCMC)", "Patna (PAT)", "Vadodara (BDQ)",
    "Ghaziabad (GZB)", "Ludhiana (LUH)", "Agra (AGR)", "Nashik (ISK)", "Faridabad (FBD)",
    "Meerut (MRT)", "Rajkot (RAJ)", "Kalyan-Dombivli (KYN)", "Vasai-Virar (BSR)", "Varanasi (VNS)",
    "Srinagar (SXR)", "Aurangabad (IXU)", "Dhanbad (DBD)", "Amritsar (ATQ)", "Navi Mumbai (NVM)",
    "Allahabad (IXD)", "Howrah (HWH)", "Ranchi (IXR)", "Gwalior (GWL)", "Jabalpur (JLR)",
    "Coimbatore (CJB)", "Vijayawada (VGA)", "Jodhpur (JDH)", "Madurai (IXM)", "Raipur (RPR)",
    "Kota (KTU)", "Chandigarh (IXC)", "Guwahati (GAU)", "Solapur (SSE)", "Hubli-Dharwad (HBX)",
    "Tiruchirappalli (TRZ)", "Bareilly (BEK)", "Moradabad (MZD)", "Mysore (MYQ)", "Tiruppur (TUP)",
    "Gurgaon (GUR)", "Aligarh (ALN)", "Jalandhar (JUC)", "Bhubaneswar (BBI)", "Salem (SXV)",
    "Warangal (WGC)", "Mira-Bhayandar (MIR)", "Jalgaon (JLG)", "Guntur (GNT)", "Thiruvananthapuram (TRV)",
    "Bhiwandi (BHW)", "Saharanpur (SRE)", "Gorakhpur (GOP)", "Bikaner (BKB)", "Amravati (AMI)",
    "Noida (NOI)", "Jamshedpur (IXW)", "Bhilai (BHL)", "Cuttack (CTC)", "Firozabad (FZD)",
    "Kochi (COK)", "Bhavnagar (BHU)", "Dehradun (DED)", "Durgapur (RDP)", "Asansol (ASN)",
    "Nanded (NDC)", "Kolhapur (KLH)", "Ajmer (KQH)", "Gulbarga (GBI)", "Loni (LON)",
    "Ujjain (UJN)", "Siliguri (IXB)", "Ulhasnagar (ULH)", "Jhansi (JHS)", "Sangli-Miraj & Kupwad (SGL)",
    "Jammu (IXJ)", "Nellore (NLR)", "Mangalore (IXE)", "Belgaum (IXG)", "Jamnagar (JGA)",
    // Key World Cities
    "New York (JFK)", "London (LHR)", "Paris (CDG)", "Tokyo (HND)", "Dubai (DXB)",
    "Los Angeles (LAX)", "Singapore (SIN)", "Hong Kong (HKG)", "Beijing (PEK)", "Sydney (SYD)",
    "Chicago (ORD)", "Frankfurt (FRA)", "Toronto (YYZ)", "Amsterdam (AMS)", "Seoul (ICN)",
    "Madrid (MAD)", "Rome (FCO)", "Bangkok (BKK)", "Istanbul (IST)", "San Francisco (SFO)",
    "Kuala Lumpur (KUL)", "Shanghai (PVG)", "Melbourne (MEL)", "Moscow (SVO)", "Miami (MIA)",
    "Jakarta (CGK)", "Sao Paulo (GRU)", "Mexico City (MEX)", "Buenos Aires (EZE)", "Johannesburg (JNB)",
    "Munich (MUC)", "Taipei (TPE)", "Manila (MNL)", "Doha (DOH)", "Las Vegas (LAS)",
    "Atlanta (ATL)", "Dublin (DUB)", "Vancouver (YVR)", "Vienna (VIE)", "Zurich (ZRH)",
    "Abu Dhabi (AUH)", "Copenhagen (CPH)", "Cape Town (CPT)", "Riyadh (RUH)", "Osaka (KIX)",
    "Boston (BOS)", "Washington D.C. (IAD)", "Cairo (CAI)", "Athens (ATH)", "Berlin (BER)"
];

function enableLocationAutocomplete(inputId) {
    const inputElement = document.getElementById(inputId);
    if (!inputElement) return;

    // Create wrapper and list elements
    const wrapper = document.createElement('div');
    wrapper.style.position = 'relative';
    wrapper.style.display = 'block'; // Make wrapper take full width
    wrapper.style.flex = '1';
    
    // Move inline styles from input to wrapper if necessary, or just rely on class
    inputElement.parentNode.insertBefore(wrapper, inputElement);
    wrapper.appendChild(inputElement);

    const suggestList = document.createElement('ul');
    suggestList.className = 'autocomplete-list';
    suggestList.style.position = 'absolute';
    suggestList.style.top = '100%';
    suggestList.style.left = '0';
    suggestList.style.width = '100%';
    suggestList.style.maxHeight = '200px';
    suggestList.style.overflowY = 'auto';
    suggestList.style.margin = '4px 0 0 0';
    suggestList.style.padding = '0';
    suggestList.style.listStyle = 'none';
    suggestList.style.background = 'rgba(25, 25, 32, 0.95)';
    suggestList.style.backdropFilter = 'blur(15px)';
    suggestList.style.border = '1px solid rgba(218, 165, 32, 0.3)';
    suggestList.style.borderRadius = '8px';
    suggestList.style.boxShadow = '0 8px 24px rgba(0, 0, 0, 0.5)';
    suggestList.style.zIndex = '1000';
    suggestList.style.display = 'none';
    
    wrapper.appendChild(suggestList);

    function closeList() {
        suggestList.style.display = 'none';
    }

    inputElement.addEventListener('input', function() {
        const val = this.value;
        suggestList.innerHTML = '';
        if (!val) {
            closeList();
            return;
        }

        let hasMatch = false;
        
        worldLocations.forEach(loc => {
            if (loc.toLowerCase().includes(val.toLowerCase())) {
                hasMatch = true;
                const li = document.createElement('li');
                li.style.padding = '10px 14px';
                li.style.cursor = 'pointer';
                li.style.color = '#fff';
                li.style.fontSize = '0.9rem';
                li.style.borderBottom = '1px solid rgba(218, 165, 32, 0.1)';
                
                // Emphasize match
                const matchIndex = loc.toLowerCase().indexOf(val.toLowerCase());
                const pre = loc.substring(0, matchIndex);
                const match = loc.substring(matchIndex, matchIndex + val.length);
                const post = loc.substring(matchIndex + val.length);
                
                li.innerHTML = `${pre}<strong style="color: #daa520;">${match}</strong>${post}`;
                
                li.addEventListener('mouseover', () => {
                    li.style.background = 'rgba(218, 165, 32, 0.15)';
                });
                li.addEventListener('mouseout', () => {
                    li.style.background = 'transparent';
                });
                
                li.addEventListener('click', () => {
                    inputElement.value = loc;
                    closeList();
                });
                
                suggestList.appendChild(li);
            }
        });
        
        if (hasMatch) {
            suggestList.style.display = 'block';
        } else {
            closeList();
        }
    });

    // Enforce selection from list on blur
    inputElement.addEventListener('change', function() {
        setTimeout(() => {
            if (!worldLocations.includes(this.value)) {
                this.value = ''; // Reset if invalid
                this.placeholder = 'Please select from suggestions';
            }
        }, 150); // slight delay to allow click event on li to fire first
    });

    document.addEventListener('click', (e) => {
        if (e.target !== inputElement) {
            closeList();
        }
    });
}

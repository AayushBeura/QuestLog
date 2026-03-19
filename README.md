# QuestLog – Smart Itinerary Management Module

## Overview

The **Itinerary Module** is the central trip-planning engine of QuestLog. It automatically organizes bookings, activities, and travel schedules into a structured timeline. Instead of manually entering travel plans, the system synchronizes confirmed bookings and intelligently builds a travel plan with financial insights, automated suggestions, and real-time scheduling assistance.

This module transforms QuestLog from a simple booking system into a **complete travel planning platform**, allowing users to manage transportation, accommodations, activities, budgets, and schedules in a single unified interface.

---

# Key Capabilities

• Automated booking synchronization
• Multi-city trip detection
• Dynamic activity recommendation engine
• Real-time budget monitoring
• Weather-aware itinerary planning
• Smart travel reminders
• Conflict detection system
• Timeline-based trip planning
• Exportable travel plans (PDF)

---

# Core Features

## 1. Smart Automation & Synchronization

### One-Click Booking Sync

The system includes a **Sync My Bookings engine** that scans all confirmed bookings in the user's account and automatically imports them into the itinerary timeline.

Supported booking types:

* Flights
* Trains
* Buses
* Hotel stays

This eliminates the need for users to manually add bookings to their itinerary.

---

### Multi-City Trip Detection

The itinerary system supports complex travel routes.

Example:

Kolkata → London → Paris → Rome

The system analyzes:

* travel dates
* booking destinations
* route continuity

and intelligently organizes the journey into sequential travel legs.

---

### Automatic Buffer Generation

When transport bookings are synchronized, the system automatically generates **travel buffer events**.

Examples include:

* Recommended airport arrival times before departure
* Transfer time between flights
* Check-in preparation reminders

These buffers help travelers plan realistic schedules.

---

### Cancellation Synchronization

If a user cancels a booking in the **My Trips section**, the itinerary automatically updates.

Automatic updates include:

* Removing the cancelled event from the timeline
* Updating itinerary cost calculations
* Recalculating trip readiness metrics

This ensures the itinerary always reflects the latest booking data.

---

# 2. Intelligent Activity Recommendation System

### Dynamic Suggestion Engine

QuestLog implements a **Dynamic Suggestion Engine** that generates activity recommendations based on the user's travel destination.

Instead of relying on external AI APIs or large language models, the system uses **context-aware server-side logic** to determine relevant activities for major travel destinations.

Example recommendations:

Paris
• Eiffel Tower Visit
• Louvre Museum Tour
• Seine River Cruise

London
• Thames River Cruise
• Buckingham Palace Tour
• London Eye Visit

Rome
• Colosseum Tour
• Vatican Museums Visit
• Roman Forum Walk

The engine analyzes the destination selected in the itinerary and dynamically returns relevant activities.

---

### LLM-Inspired Suggestion Behavior

The Dynamic Suggestion Engine mimics the behavior of an **AI-based recommendation system** while remaining fully self-contained.

It provides:

* Context-based activity suggestions
* Real-time recommendations
* Estimated activity costs

Because the system runs locally within the application logic, it offers:

* Instant response times
* No dependency on external APIs
* Zero additional API cost

This makes the system highly responsive and scalable.

---

### Unified Smart Activity Search

Instead of traditional dropdown inputs, the itinerary planner provides a **single intelligent search field**.

As the user types:

* suggested activities appear dynamically
* estimated costs are displayed
* relevant activities appear based on the selected destination

This simplifies activity discovery.

---

### Auto-Fill Activity Creation

When a suggested activity is selected, the system automatically fills:

* Activity title
* Estimated cost
* Default description

Users can then adjust the time, schedule, or notes.

---

# 3. Financial Intelligence

### Live Remaining Budget

Users can define a **trip budget** during itinerary creation.

The system continuously calculates:

Remaining Budget = Budget − Total Expenses

The remaining amount is displayed in real time at the top of the itinerary dashboard.

---

### Total Expense Tracking

The itinerary automatically aggregates costs from:

* Transport bookings
* Hotel reservations
* Added activities

This generates a complete **trip expense summary**.

---

### Smart Over-Budget Alerts

If expenses exceed the defined budget:

* The remaining budget indicator turns red
* Visual alerts notify the user

This helps travelers maintain financial control during planning.

---

# 4. Intelligent Travel Assistance

### Weather Forecast Integration

The itinerary integrates **weather forecasting** for the selected travel destination.

For each day of the trip, users can view:

* Weather condition
* Temperature forecast
* Weather icons

This allows travelers to schedule activities based on expected weather conditions.

---

### Smart Reminder Notifications

The system provides **automatic reminders for upcoming travel events**.

Examples include:

* Flight departures
* Hotel check-in times
* Scheduled activities
* Important transport events

These reminders help users stay organized during their journey.

---

### Gamified Trip Readiness Score

The itinerary includes a **Trip Planning Score** that measures how prepared a trip is.

The score is calculated based on:

* Transport bookings added
* Hotel bookings added
* Activities planned
* Budget defined

Example display:

Trip Readiness: 75%

This encourages users to complete their trip planning.

---

### Smart Conflict Detection

The system detects scheduling conflicts within the itinerary timeline.

Examples include:

* Activities scheduled during flight times
* Overlapping activities
* Activities scheduled before arrival

When conflicts are detected, warning alerts are displayed.

Example:

⚠ Activity overlaps with flight departure.

---

# 5. Trip Management Features

### Source & Destination Tracking

Each itinerary tracks both the **starting location and final destination**, enabling accurate representation of round-trip travel plans.

This improves:

* transport mapping
* multi-city routing
* trip visualization

---

### Full Trip Control

Users have full control over itinerary management.

Available actions include:

* Create itinerary
* Edit itinerary details
* Delete trip

Deleting a trip removes associated timeline entries and activities from the system.

---

### Timeline-Based Trip Planner

The itinerary is presented as a **day-by-day timeline interface**.

Each day contains scheduled events including:

* Transport departures
* Hotel stays
* Activities
* Travel buffers

Example timeline:

Day 1 – Travel
Day 2 – City exploration
Day 3 – Planned activities

Each event is displayed with timestamps for clear visualization.

---

# 6. Data Export & Travel Documentation

### Export Full Travel Plan (PDF)

Users can export their entire itinerary as a **PDF travel document**.

The generated document includes:

* Trip overview
* Transport bookings
* Hotel reservations
* Activity schedule
* Budget and expense summary

This allows travelers to keep an offline copy of their trip plan or share it easily.

---

# System Architecture of the Itinerary Engine

The QuestLog Itinerary Module uses a **hybrid backend architecture combining PHP, Node.js, and MySQL**.

### PHP Backend

PHP handles the core application logic including:

* itinerary creation and management
* booking synchronization
* cost calculations
* activity recommendation logic
* timeline generation

PHP communicates with the MySQL database using secure **PDO-based queries**.

---

### MySQL Database

MySQL stores all persistent itinerary-related data including:

* itinerary records
* daily trip schedules
* timeline events
* activity data
* cost tracking

This structured data model ensures efficient storage and retrieval of travel plans.

---

### Node.js Integration

Node.js powers the **real-time features of QuestLog**, including:

* QR payment confirmation
* instant booking updates
* live dashboard notifications

When a booking is confirmed through the payment system, Node.js events trigger updates that can immediately reflect in the itinerary timeline.

---

### Frontend Layer

The user interface is built using:

* HTML5
* CSS3
* Vanilla JavaScript

The frontend provides:

* interactive itinerary timelines
* dynamic activity suggestions
* real-time budget calculations
* travel notifications

Frontend components communicate with backend APIs to dynamically update the travel plan.

---

# Database Schema for the Itinerary Engine

The itinerary system relies on several relational database tables to organize travel plans efficiently.

### itineraries

Stores basic trip information.

Fields:
```
id
user_id
trip_name
source
destination
start_date
end_date
budget
created_at
```
---

### itinerary_days

Stores generated days for each itinerary.

Fields:
```
id
itinerary_id
day_number
date
```
This allows events to be mapped to specific trip days.

---

### timeline_events

Stores all scheduled items within the itinerary timeline.

Fields:
```
id
itinerary_id
day_id
event_type
title
description
start_time
end_time
cost
reference_id
```
Event types may include:
```
transport
hotel
activity
buffer
```
---

### activities

Stores manually added or suggested activities.

Fields:
```
id
itinerary_id
activity_name
location
date
time
estimated_cost
notes
```
---

# Benefits of the Itinerary Module

The itinerary module transforms QuestLog into a **complete travel management platform**.

Key benefits include:

* automated trip organization
* intelligent travel suggestions
* real-time financial tracking
* seamless booking synchronization
* structured travel planning

---

# Conclusion

The QuestLog Itinerary Module acts as the **central intelligence layer of the travel platform**, automatically integrating bookings, financial tracking, and activity planning into a unified travel timeline. By combining automation, smart recommendations, and structured scheduling, the module provides users with a powerful and intuitive travel planning experience.

Tsoft Traffic Density Manager
TRAFFIC MONITORING AND TRAFFIC LIGHT CONTROL SUPPORT SYSTEM

📌 Project Overview

Tsoft Traffic Density Manager is a web-based traffic monitoring and traffic light control support system that uses vehicle density data extracted from camera images.
The system enables:
- Traffic density monitoring
- Vehicle counting from camera images using AI (YOLOv8)
- Data-driven traffic light control (automatic & manual)
- Visual display of traffic lights, countdown timers, and traffic statistics
- This project is oriented toward Intelligent Transportation Systems (ITS), aiming to reduce congestion and improve traffic flow efficiency.

🎯 System Objectives

- Collect traffic density data from images
- Provide a web-based monitoring dashboard
- Support decision-making for traffic light control
- Serve as a foundation for future intelligent control algorithms

🚀 Main Features

1️⃣ User Management

The system supports role-based access control:
Admin
- Login to the system
- Monitor traffic data
- Control traffic lights (automatic or manual mode)

Guest
- View traffic data and traffic light status only
- No permission to control or modify the system

➡️ This ensures system security and proper authorization.

2️⃣ Traffic Monitoring
- Receives traffic density data from a Python vehicle counting script
- Collects data for 4 directions: North, South, East, West
- Uses Web APIs to update data in real time
- Displays vehicle counts and traffic density on the web interface

3️⃣ Traffic Light Control
The system supports two control modes:

🔹 Automatic Control
Traffic light timing is adjusted based on traffic density

Directions with higher vehicle volume receive longer green time

Helps reduce congestion and improve traffic flow

🔹 Manual Control (Admin)

Admin can manually override traffic lights

Used for special situations such as peak hours or emergencies

4️⃣ Data Visualization & Statistics
Displays:
- Traffic light status
- Countdown timers
- Traffic density for each direction
- Traffic data is visualized using charts to:
- Compare traffic density
- Analyze traffic trends over time

5️⃣ Vehicle Counting from Images

- Images are collected from 4 folders: bac/, nam/, dong/, tay
- Uses YOLOv8 to detect and count vehicles
- Converts vehicle count into vehicles per minute
- Sends data to the web server via API

🗂️ Project Structure

<img width="635" height="464" alt="image" src="https://github.com/user-attachments/assets/5b21bad2-23f0-4395-a386-e86879ec7f96" />


⚙️ System Requirements & Installation
🔹 Web Server & Database

XAMPP (recommended)

Apache Web Server

MySQL / MariaDB

PHP ≥ 7.x
MySQL port: 3307

Web server port:
Default: 80

If port 80 is unavailable, change to 8080 or another port

📌 Apache port can be configured in httpd.conf.

🔹 Python Environment
Required libraries:
pip install ultralytics

pip install requests

The Python script uses:
- YOLOv8 (ultralytics)
- requests, os, time, random

🔄 System Workflow

Start XAMPP

Run Apache

Run MySQL (Database port: 3307)

Open phpMyAdmin

Ex: http://localhost:8080/phpmyadmin

Open the web system in a browser

Ex: http://localhost:8080/giaothong/index.php

Run the Python script:

python traffic_counter.py

The Python script:
- Checks image folders for each direction
- Randomly selects one image per direction
- Uses YOLOv8 to count vehicles
- Converts the count to vehicles per minute
- Traffic data is sent to the Web API
The website:
- Receives and stores traffic data
- Displays traffic density and traffic light status
- Supports traffic light control
- The process repeats periodically (default: every 20 seconds)

🧪 Error Handling & Safety

The Python script detects and logs errors when:

Image folders do not exist

No images are found

API connection fails

Errors do not stop the system

Web access is controlled based on user roles

🧠 Future Improvements

Intelligent traffic light control algorithms (AI, Fuzzy Logic, Reinforcement Learning)

Real-time camera integration (IP cameras)

Multi-intersection support

Map integration (Google Maps / OpenStreetMap)

📞 Contact & Contribution

Email: thuanthuan8a3@gmail.com
Email: trantin2114@gmail.com


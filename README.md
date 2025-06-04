# 📘 AI-Powered Smart Study Planner

The **AI-Powered Smart Study Planner** is a web-based application designed to help students intelligently manage their study schedules. By integrating artificial intelligence, gamification, and visual analytics, this tool allows students to plan, track, and improve their learning effectively. It dynamically allocates study time based on subject difficulty, user priorities, and deadlines — making study sessions more productive and personalized.

---

## 🚀 Features

### Students
- **Account Creation**: Students can sign up by providing their name, email, password, year, department, and profile photo.
- **Login & Profile Management**: Students can log in, view and edit their study preferences, and manage their profile.
- **Study Plan Generator**: Automatically generates a personalized study schedule using an AI-driven weighted priority algorithm.
- **Gamified Progress**: Earn points, badges, and levels based on completion of study goals.
- **History & Reports**: View history of study activities and progress over time.

### Intelligent System Features
- **Weighted Priority Algorithm**: Allocates study time based on difficulty level, subject importance, and remaining days.
- **Data Visualization**: Graphs and charts to track study performance, pending vs completed tasks, and daily efficiency.
- **Natural Language Support (Optional)**: Accepts basic commands in plain English to adjust schedules.
- **Adaptive Feedback Loop**: Learns from user input and completion rates to dynamically improve future planning.
- **Multi-Device & Offline Sync**: Access your planner from any device and stay updated even while offline.

---

## 🖼️ Screenshots

### 1. 🧑‍🎓 Student Dashboard
A personalized dashboard showing an overview of study progress, current goals, and rewards.

![image](https://github.com/user-attachments/assets/8ccd99e2-6c8c-4d46-a0be-7eaefc49e07b)


---

### 2. 📅 Study Planner
The dynamic planner intelligently schedules subjects based on your input and AI priorities.

![image](https://github.com/user-attachments/assets/2629ed51-2acc-458b-bd59-49abb1dbc888)


---
### 3. 📚 Generated Study Plan
Study Plan is generated in table format along with the feature of download plan in pdf format.

![image](https://github.com/user-attachments/assets/0fb59990-586b-4638-aa1d-b8cc26749dd0)


---

### 4. 📚 Study History
Visualize and track past study sessions along with time spent and completion status.

![image](https://github.com/user-attachments/assets/6bb0e79b-6766-44fd-aaf9-f984fd0f432f)


---

### 5. 📈 Progress Tracking
See real-time graphs and metrics for your study performance and improvement areas.

![image](https://github.com/user-attachments/assets/3547146b-c999-4897-a614-69f878bf47f1)


---

## 🗃️ Database Schema

### Tables
1. **users**:
   - Stores student details such as name, email, password, year, department, and profile photo.

2. **study_plans**:
   - Contains scheduled study sessions with subject, date, duration, and status.

3. **study_history**:
   - Logs completed sessions and user feedback for adaptive planning.

4. **achievements**:
   - Stores gamification data including points, levels, and badges.

---

## ⚙️ Installation

1. **Setup Environment**:
   - Use a local server like WAMP, XAMPP, or MAMP.

2. **Import Database**:
   - Import `smart_study_planner.sql` into your MySQL database.

3. **Configure Files**:
   - Update database connection details (`host`, `user`, `password`, `dbname`) in `config.php`.

4. **Run Application**:
   - Place all project files into the server directory (`htdocs` or `www`).
   - Launch the browser and open `http://localhost/smart-study-planner`.

---

## 🧑‍💻 Usage

1. **Student Workflow**:
   - Register and log in.
   - Enter subjects, difficulty levels, and deadlines.
   - Get a smart plan and follow daily goals.
   - Track performance and gain points.

2. **System Workflow**:
   - AI prioritizes tasks and allocates optimal study slots.
   - Visual feedback helps students understand their focus and progress.
   - Adaptive logic adjusts future plans based on behavior.

---

## 💻 Technologies Used

- **Frontend**: HTML, CSS, JavaScript
- **Backend**: PHP
- **Database**: MySQL
- **Libraries**: Chart.js (Data Visualization), PHPMailer (Notifications), LocalStorage/IndexedDB (Offline Access)

---

## 🔮 Future Enhancements

- Email reminders for daily study goals.
- Real-time collaboration and peer competition.
- Integration with Google Calendar.
- Mobile app version using React Native or Flutter.

---

## 📜 License

MIT License

Copyright (c) 2025 Subbiah-Karthick-S

Permission is hereby granted, free of charge, to any person obtaining a copy  
of this software and associated documentation files (the "Software"), to deal  
in the Software without restriction, including without limitation the rights  
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell  
copies of the Software, and to permit persons to whom the Software is  
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all  
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR  
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,  
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE  
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER  
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,  
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE  
SOFTWARE.

---

## 📬 Contact

For any questions or suggestions, feel free to reach out:

- **Email**: [subbiahkarthickcse@gmail.com](mailto:subbiahkarthickcse@gmail.com)  
- **GitHub**: [Subbiah-Karthick-S](https://github.com/Subbiah-Karthick-S)

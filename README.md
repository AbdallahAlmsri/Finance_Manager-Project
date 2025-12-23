# Finance Manager Project

The Finance Manager Project is a web-based personal finance management system designed to help users manage their financial activities in an organized and efficient way. The system allows users to manage budgets, financial goals, transactions, payments, and receive automated notifications.

This project was developed using modern web development practices and a cloud-hosted database.

---

## Technologies Used

- PHP (Backend)
- PostgreSQL (Neon Cloud Database)
- HTML, CSS, JavaScript (Frontend)
- XAMPP (Apache + PHP for local development)

---

## Project Structure

Finance_Manager/
│
├── includes/ # Backend logic and database connection
├── public/ # Public-facing pages and UI
├── .env.example # Environment variables template
└── .gitignore

---

## Database Configuration

The project uses a PostgreSQL database hosted on Neon (cloud).

Database credentials are not stored directly in the repository. Instead, the application reads them from environment variables stored in a `.env` file.

---

## Environment Variables Setup

1. Create a file named `.env` in the root directory of the project.
2. Copy the contents of `.env.example` into `.env`.
3. Fill in your database credentials as shown below:
   
    DB_HOST=your_database_host
    DB_NAME=your_database_name
    DB_USER=your_database_user
    DB_PASS=your_database_password
    DB_PORT=5432
    DB_SSL=require
    DB_ENDPOINT=your_neon_endpoint


The `.env` file is ignored by Git for security reasons.

---

## How to Run the Project Locally

1. Install XAMPP
2. Place the project folder inside:
      C:\xampp\htdocs\
3. Start Apache from the XAMPP Control Panel
4. Configure the `.env` file
5. Open a browser and visit:
      http://localhost/Finance_Manager/public/

---

## Security Notes

- Database credentials are stored securely using environment variables
- This project follows basic security best practices suitable for portfolio use
- The `.env` file is excluded from version control
- A `.env.example` file is provided for configuration guidance

---

## Screenshots

The following screenshots demonstrate the main user interface pages and real database tables (captured directly from the cloud-hosted Neon PostgreSQL) used by the application.


### Application Screenshots

- Login/Signup

  <img width="1919" height="909" alt="Screenshot 2025-12-23 200600" src="https://github.com/user-attachments/assets/ba609203-baf7-4ed7-808b-b11fc2e7996f" />
  <img width="1919" height="910" alt="Screenshot 2025-12-23 200622" src="https://github.com/user-attachments/assets/959e6c84-0947-461c-9dac-cafdf0d2af40" />
  
- Dashboard

<img width="1919" height="913" alt="Screenshot 2025-12-23 200857" src="https://github.com/user-attachments/assets/e3d858d3-1ce8-4968-8214-4e83943dc51c" />
<img width="1919" height="907" alt="Screenshot 2025-12-23 200909" src="https://github.com/user-attachments/assets/88a20441-c3c5-4e4d-956e-7569118938f9" />

- Transactions Page

<img width="1919" height="915" alt="Screenshot 2025-12-23 201059" src="https://github.com/user-attachments/assets/45554439-aa99-44b6-b3f1-2207ccd0d41e" />
<img width="1919" height="912" alt="Screenshot 2025-12-23 201126" src="https://github.com/user-attachments/assets/1ccbfcb7-b16c-4136-b8b9-9566cca5ae07" />

- Budgets Page

<img width="1919" height="909" alt="Screenshot 2025-12-23 201257" src="https://github.com/user-attachments/assets/fe41345d-4648-486d-bb0c-ea8718d8c7aa" />
<img width="1919" height="910" alt="Screenshot 2025-12-23 201319" src="https://github.com/user-attachments/assets/355be2e3-52a8-4184-a278-2a78a915de30" />

- Reports Page

<img width="1919" height="907" alt="Screenshot 2025-12-23 201412" src="https://github.com/user-attachments/assets/8c443514-7f5d-4086-96fe-a49473de5cb4" />
<img width="1919" height="910" alt="Screenshot 2025-12-23 201423" src="https://github.com/user-attachments/assets/9c6d86fe-ed4f-484e-ac5f-4dc7f001df53" />
<img width="1911" height="995" alt="Screenshot 2025-12-23 201540" src="https://github.com/user-attachments/assets/c83cdcbc-07e1-4a01-b5bc-6eba15e366b9" />

- Savings Goals Page

<img width="1919" height="911" alt="image" src="https://github.com/user-attachments/assets/d2917824-2d2e-4eed-8e18-b85e509ce855" />

- Payments Page
<img width="1919" height="912" alt="Screenshot 2025-12-23 201814" src="https://github.com/user-attachments/assets/709c35d2-288f-40da-adc1-5a27e46e370a" />
<img width="1919" height="916" alt="Screenshot 2025-12-23 201825" src="https://github.com/user-attachments/assets/d2f7cf63-4246-4837-a565-b9c3b6ecad44" />

- Notifications Page

<img width="1919" height="911" alt="Screenshot 2025-12-23 201949" src="https://github.com/user-attachments/assets/627602c9-660f-4c83-8920-3b00f97e63cd" />
<img width="1919" height="914" alt="Screenshot 2025-12-23 202007" src="https://github.com/user-attachments/assets/38b0e2d1-2f0d-4e0d-90f3-71e755a4df1b" />

- Calendar Page

<img width="1919" height="912" alt="Screenshot 2025-12-23 202138" src="https://github.com/user-attachments/assets/653c1af6-91bf-43f9-8a16-7acd54fc2e72" />
<img width="1919" height="907" alt="Screenshot 2025-12-23 202226" src="https://github.com/user-attachments/assets/96479b53-c988-4709-87be-5a8470d0ec4e" />

- Settings Page

<img width="1919" height="912" alt="Screenshot 2025-12-23 202325" src="https://github.com/user-attachments/assets/d6b96f77-61fd-41ba-8d06-57c3c3ef5aae" />
<img width="1919" height="912" alt="Screenshot 2025-12-23 202341" src="https://github.com/user-attachments/assets/75a174ab-527e-41c3-a09a-b8272ea63d1c" />

- Dashboard Page (In Dark Mode)

<img width="1915" height="911" alt="Screenshot 2025-12-23 202449" src="https://github.com/user-attachments/assets/54d2e1cc-afda-4e5c-983b-d7ecd13416aa" />
<img width="1919" height="916" alt="Screenshot 2025-12-23 202511" src="https://github.com/user-attachments/assets/988f20a8-75f7-4fcb-838c-ff3402e201e7" />


### Database Tables (PostgreSQL)
- Budgets table

<img width="1919" height="910" alt="Screenshot 2025-12-23 202801" src="https://github.com/user-attachments/assets/e1374cfa-9896-4276-bf2e-c3d16cfb3971" />

- Goals table

<img width="1919" height="913" alt="Screenshot 2025-12-23 202810" src="https://github.com/user-attachments/assets/29d3c258-fa4f-4ecc-b8bc-bc909cde493a" />

- Notification Settings table

<img width="1919" height="917" alt="Screenshot 2025-12-23 202818" src="https://github.com/user-attachments/assets/2d96ad06-8afa-43c3-81fc-9305c8e913ff" />

- Notifications table

<img width="1919" height="915" alt="Screenshot 2025-12-23 202838" src="https://github.com/user-attachments/assets/065f8f95-2968-4946-940f-291790cf25ae" />

- Payments table

<img width="1919" height="923" alt="Screenshot 2025-12-23 202848" src="https://github.com/user-attachments/assets/c6845cbf-451d-4064-8c6a-a999eba6366b" />

- Transactions table

<img width="1919" height="909" alt="Screenshot 2025-12-23 202856" src="https://github.com/user-attachments/assets/951f1b8e-63fe-4771-ac8d-c1285dcbc329" />

- Users table

<img width="1919" height="917" alt="Screenshot 2025-12-23 202907" src="https://github.com/user-attachments/assets/78861579-fc88-434e-be87-262ec7b7123a" />

---

## Features

### Core Features
- [x] User authentication and session management
- [x] Secure connection to a cloud-hosted PostgreSQL database (Neon)
- [x] Environment-based database configuration using `.env`

### Transactions
- [x] Add income transactions
- [x] Add expense transactions
- [x] Categorize transactions
- [x] Attach notes to transactions
- [x] View all transactions in a table
- [x] Automatic calculation of total income and expenses
- [x] Real-time balance calculation based on transactions

### Dashboard
- [x] Display total balance
- [x] Display total income
- [x] Display total expenses
- [x] Show recent transactions
- [x] Spending breakdown by category (chart)

### Budgets
- [x] Create budgets by category
- [x] Set budget limits and periods (monthly / weekly / yearly)
- [x] Track spending against budgets
- [x] Visual budget progress indicators
- [x] Automatic budget usage percentage calculation
- [x] Quick add expense directly from budget view
- [x] Budget-based notifications

### Savings Goals
- [x] Create savings goals with target amounts
- [x] Track saved amount toward goals
- [x] Add savings via modal (connected to transactions)
- [x] Visual progress bars for goals
- [x] Goal progress notifications

### Payments
- [x] Add recurring payments (loans, mortgages, etc.)
- [x] Track total vs paid amounts
- [x] Record payment expenses
- [x] Automatic update of remaining balance
- [x] Payment reminder notifications

### Reports
- [x] Date-range based financial reports
- [x] Expense breakdown by category
- [x] Income vs expense comparison
- [x] Drill-down into category transactions
- [x] Compare current period with previous period
- [x] Export reports to CSV (Excel-compatible)
- [x] Include transaction dates in exported reports

### Notifications System
- [x] Budget warning notifications
- [x] Goal progress notifications
- [x] Bill payment reminders
- [x] Low balance alerts
- [x] Notification settings stored in database
- [x] Automatic notification generation based on real data
- [x] Prevent duplicate notifications
- [x] Display recent notifications from database

### Application Settings
- [x] Currency selection
- [x] Date format selection
- [x] Number format selection
- [x] Toggle currency symbol display
- [x] Toggle thousands separator
- [x] Compact table view option
- [x] Live preview of settings
- [x] Settings persistence using browser storage
- [x] Settings applied across all pages dynamically

### Security & Best Practices
- [x] Prepared SQL statements (SQL injection prevention)
- [x] Environment variables for sensitive credentials
- [x] `.env` excluded from version control
- [x] `.env.example` provided for setup
- [x] Separation of backend logic and frontend UI

### Development & Deployment
- [x] Local development using XAMPP
- [x] Cloud database integration (Neon)
- [x] GitHub repository with clean structure
- [x] Ready for further extension and deployment










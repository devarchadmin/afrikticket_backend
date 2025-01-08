<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

# AfrikTicket Platform

## About AfrikTicket

AfrikTicket is a comprehensive event ticketing and fundraising platform built with Laravel. It enables organizations to:
- Create and manage events
- Sell tickets
- Run fundraising campaigns
- Track donations and revenues
- Generate detailed analytics

## Core Features

### Authentication System
- Multi-role user system (User, Organization, Admin)
- Secure token-based authentication
- Organization verification process

### Event Management
- Create and manage events
- Ticket sales and validation
- Event scheduling and tracking
- Image uploads and management

### Fundraising System
- Create fundraising campaigns
- Track donations and progress
- Campaign status management
- Real-time analytics

### Organization Dashboard
- Revenue tracking
- Event performance metrics
- Donation analytics
- Document management

### Admin Panel
- User management
- Content moderation
- Organization approval
- System statistics

## Requirements

- PHP >= 8.1
- Composer
- MySQL/MariaDB
- Node.js & NPM
- Laravel CLI

## Quick Setup

1. Clone the repository:
```bash
git clone "repourl"
cd afrikticket_back
```
2. Install dependencies:

```bash
composer install
npm install
```

3. Environment setup:
```bash
cp .env.example .env
php artisan key:generate
```
4. Configure your .env file with database credentials and other settings:

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

JWT_SECRET=your_jwt_secret

5. Run migrations: 
```bash
php artisan migrate
```
```bash
php artisan serve
```

API Documentation

## Base URL
http://localhost:8000/api

## User Types
1. Regular User
2. Organization
3. Admin

## Key Features

### 1. Authentication
- Registration (`POST /register`)
- Login (`POST /login`)
- Logout (`POST /logout`) [Protected]
- Get User Profile (`GET /user`) [Protected]

### 2. Events
- List Events (`GET /events`)
- Get Single Event (`GET /events/{id}`)
- Create Event (`POST /events`) [Organization Only]
- Update Event (`PUT /events/{id}`) [Organization Only]
- Delete Event (`DELETE /events/{id}`) [Organization Only]
- Organization Events (`GET /org/events`) [Organization Only]
- User Events (`GET /user/events`) [Protected]

### 3. Tickets
- Purchase Tickets (`POST /events/{eventId}/tickets`) [Protected]
- Validate Ticket (`POST /tickets/validate`) [Organization Only]
- User Tickets (`GET /user/tickets`) [Protected]

### 4. Fundraising
- List Fundraisings (`GET /fundraising`)
- Get Single Fundraising (`GET /fundraising/{id}`)
- Create Fundraising (`POST /fundraising`) [Organization Only]
- Update Fundraising (`PUT /fundraising/{id}`) [Organization Only]
- Organization Fundraisings (`GET /org/fundraisings`) [Organization Only]

### 5. Donations
- Make Donation (`POST /fundraising/{fundraisingId}/donate`) [Protected]
- User Donations (`GET /user/donations`) [Protected]

### 6. Organization Dashboard
- Get Dashboard Stats (`GET /organization/dashboard`) [Organization Only]
  - Events statistics
  - Revenue data
  - Ticket sales
  - Fundraising progress

### 7. Admin Features
- Dashboard Stats (`GET /admin/dashboard/stats`)
- User Management (`GET /admin/users`)
- Organization Management
  - List (`GET /admin/organizations`)
  - Review (`PUT /admin/organizations/{id}/status`)
  - Delete (`DELETE /admin/organizations/{id}`)
- Content Moderation
  - Pending Content (`GET /admin/pending`)
  - Review Event (`PUT /admin/events/{id}/review`)
  - Review Fundraising (`PUT /admin/fundraisings/{id}/review`)

## Important Notes

### Organization Registration
- Organizations must upload required documents (ICD & commerce register)
- Organization accounts require admin approval before activation
- Status can be: pending, approved, or rejected

### Event Creation
- Events require admin approval before becoming visible
- Include image uploads
- Set ticket quantities and pricing
- Status can be: pending, active, or cancelled

### Fundraising Campaigns
- Require admin approval
- Include goal amount and description
- Track progress and donations
- Status can be: pending, active, completed, or cancelled

### File Upload Requirements
- Images: jpeg, png, jpg (max 2MB)
- Documents: pdf, jpg, jpeg, png (max 2MB)

## Testing Locally
1. Clone the repository
2. Install dependencies: `composer install`
3. Copy `.env.example` to `.env` and configure database
4. Run migrations: `php artisan migrate`
5. Generate app key: `php artisan key:generate`
6. Start server: `php artisan serve`

## Postman Collection
A complete Postman collection will be provided separately with:
- Pre-configured environments
- Request examples
- Test data
- Authentication flows

## Error Handling
The API returns consistent error responses:
```json
{
    "status": "error",
    "message": "Error description"
}
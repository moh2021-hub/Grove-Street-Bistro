
# Grove Street Bistro

A restaurant ordering website built with **PHP, MySQL, JavaScript, HTML, and CSS**. It includes a customer-facing menu and ordering flow, account authentication, and an admin area for managing menu items and orders.

## Features

- Browse restaurant menu items
- Register, log in, and log out
- Add items to a shopping cart and proceed to checkout
- Place orders and view order history/status
- Admin dashboard for managing menu items and orders
- JSON API endpoints for menu, cart, orders, and admin operations
- Responsive styling

## Tech Stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8+ |
| Database | MySQL (PDO) |
| Frontend | HTML, CSS, JavaScript |
| Local Server | Apache (XAMPP) |
| Deployment | AWS EC2 with Amazon RDS (optional) |

## Getting Started

### 1. Clone the Repository

```bash
git clone https://github.com/YOUR_USERNAME/YOUR_REPOSITORY.git
```

Place the project folder inside your local web server directory, such as `htdocs` for XAMPP.

### 2. Configure MySQL

Start Apache and MySQL, then create a database:

```sql
CREATE DATABASE restaurant_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

**Note:** The project archive does not include a SQL schema or database dump. The required database tables must be created before the application can function.

### 3. Configure Database Credentials

Copy `config/local.example.php` to `config/local.php`.

Update the configuration:

```php
<?php
declare(strict_types=1);

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'restaurant_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_NAME', 'Grove Street Bistro');
define('APP_DEBUG', true);
```

Do not commit real passwords or `config/local.php` to GitHub.

### 4. Run the Application

Start Apache and MySQL.

Open the following URL in your browser:

```text
http://localhost/Grove%20Street%20Bistro/
```

## Project Structure

```text
Grove Street Bistro/
├── admin/                 # Admin dashboard and management
├── api/                   # PHP API endpoints
├── assets/
│   ├── css/               # CSS stylesheets
│   └── js/                # JavaScript files
├── config/                # Database configuration
├── includes/              # Shared PHP components
├── uploads/               # Uploaded images
├── index.php              # Homepage
├── menu.php               # Restaurant menu
├── cart.php               # Shopping cart
├── checkout.php           # Checkout
├── orders.php             # Order history
├── login.php              # User login
├── register.php           # User registration
└── logout.php             # User logout
```

## Deployment

The project can be configured for deployment using:

- **Amazon EC2** for hosting the PHP application
- **Amazon RDS** for the MySQL database
- **Apache** as the web server
- **HTTPS** for secure communication

For production, disable debugging and secure all database credentials.

## Current Limitations

- Database schema and seed data are not included.
- The application has not been verified as deployed or tested in this environment.

## License

No license file was included in the supplied project.

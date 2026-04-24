<div align="center">

# 🎓 AmoAcademy

**E-learning platform for buying and managing online design courses**

[![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![React](https://img.shields.io/badge/React-20232A?style=for-the-badge&logo=react&logoColor=61DAFB)](https://reactjs.org)
[![MySQL](https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![PayPal](https://img.shields.io/badge/PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=white)](https://paypal.com)

</div>

---

## 📖 About

A full-stack web application that enables **course creators** to sell digital content and **students** to purchase and access educational materials. Built from scratch with complete payment integration and hosting setup.

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | Laravel, MySQL |
| **Frontend** | React |
| **Payments** | PayPal, UPC |
| **Infrastructure** | Hetzner, Cloudflare R2 |

---

## ✨ Key Features

- 📚 **Course Management** — Full course lifecycle with enrollment tracking
- 💳 **Payment Integration** — Multiple payment processors (PayPal, UPC)
- 🔐 **Auth & Authorization** — Secure user authentication and role management
- 🚀 **Automated Deployment** — CI/CD pipeline for seamless releases
- ☁️ **Cloud Media Storage** — R2 CloudStorage for all media assets

---

## 🚀 Getting Started

### Prerequisites

- PHP `>= 8.1`
- Composer
- Node.js & npm
- MySQL

### Installation

```bash
# Clone the repository
git clone https://github.com/your-username/amoacademy.git
cd amoacademy

# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start development servers
php artisan serve
npm run dev
```

---

## ⚙️ Environment Variables

Configure the following in your `.env` file:

```env
DB_DATABASE=amoacademy
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_SECRET=your_paypal_secret

CLOUDFLARE_R2_BUCKET=your_bucket_name
CLOUDFLARE_R2_ACCESS_KEY=your_access_key
CLOUDFLARE_R2_SECRET_KEY=your_secret_key
```

---

<div align="center">

Made with ❤️ · AmoAcademy

</div>

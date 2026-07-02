# LifeMed CRM

**Medical Clinic Management System** — a full-featured web application for managing patients, appointments, services, lab results, and Telegram bot integration.

---

## 🇬🇧 English

### About

LifeMed CRM is a comprehensive clinic management system built for medical facilities. It streamlines patient registration, appointment scheduling, service management, lab result tracking, and financial reporting — all with a built-in Telegram bot for real-time notifications.

### Features

- **Patient Management** — Register, search, and maintain patient records with contact info and history
- **Doctor Panel** — Doctors can view their daily appointments and manage schedules
- **Appointment & Registration** — Book appointments, assign doctors and services, generate receipt numbers
- **Service Catalog** — Manage services, directions (УЗИ, ЭКГ, etc.), and service groups with pricing and commission
- **Lab Results** — Create lab templates (HTML-based), fill in results, and send them to patients via Telegram
- **Receipt System** — Generate PDF receipts, send them to patients through Telegram bot
- **Financial Reports** — Daily, weekly, monthly reports with doctor-wise breakdowns
- **Telegram Bot** — Patients get receipts & lab results; doctors get appointment notifications; admin gets daily reports and database backups
- **Role-Based Access** — Admin, Cashier, Doctor roles with different permissions
- **PWA Support** — Works as a Progressive Web App on mobile devices
- **Dark Mode** — Built-in light/dark theme toggle
- **Audit Log** — Tracks all user actions for security
- **Database Backup** — Create and download SQL backups from the admin panel or Telegram bot

### Tech Stack

- **Backend:** PHP 8+ with PDO (MySQL)
- **Frontend:** Bootstrap 5, Vue.js, Font Awesome
- **Database:** MySQL 8.0
- **Bot:** Telegram Bot API (webhook-based)
- **PDF:** SimplePdf library

### Author

**Sherzod Islomov** — Developer from Kasansay, Uzbekistan

---

## 🇷🇺 Русский

### О проекте

LifeMed CRM — это комплексная система управления медицинской клиникой. Упрощает регистрацию пациентов, запись на приёмы, управление услугами, отслеживание результатов анализов и финансовую отчётность — всё с интеграцией Telegram-бота для уведомлений в реальном времени.

### Возможности

- **Управление пациентами** — Регистрация, поиск, ведение карточек с контактами и историей
- **Панель врача** — Врач видит свои приёмы на сегодня, управление расписанием
- **Запись и регистрация** — Оформление приёмов, назначение врачей и услуг, номера квитанций
- **Каталог услуг** — Управление услугами, направлениями (УЗИ, ЭКГ и т.д.), группами услуг с ценами и комиссией
- **Результаты анализов** — Шаблоны анализов (HTML), заполнение результатов, отправка пациентам через Telegram
- **Система чеков** — Генерация PDF-квитанций, отправка через Telegram-бот
- **Финансовые отчёты** — Дневные, недельные, месячные отчёты с разбивкой по врачам
- **Telegram-бот** — Пациенты получают чеки и анализы; врачи — уведомления о записях; начальник — ежедневные отчёты и бэкапы БД
- **Ролевой доступ** — Админ, Кассир, Врач с разными правами
- **PWA** — Работает как Progressive Web App на мобильных
- **Тёмная тема** — Переключатель светлой/тёмной темы
- **Аудит** — Журнал действий всех пользователей
- **Бэкап БД** — Создание и скачивание SQL-бэкапов из панели или Telegram

### Технологии

- **Backend:** PHP 8+ с PDO (MySQL)
- **Frontend:** Bootstrap 5, Vue.js, Font Awesome
- **База данных:** MySQL 8.0
- **Бот:** Telegram Bot API (webhook)
- **PDF:** Библиотека SimplePdf

### Автор

**Шерзод Исломов** — Разработчик из Касансая, Узбекистан

---

## 🇺🇿 O'zbekcha

### Loyiha haqida

LifeMed CRM — tibbiyot klinikasini boshqarish uchun to'liq tizim. Bemorlarni ro'yxatdan o'tkazish, qabulga yozish, xizmatlarni boshqarish, tahlil natijalarini kuzatish va moliyaviy hisobot berish — barchasi Telegram boti orqali real vaqtda bildirishnomalar bilan.

### Imkoniyatlar

- **Bemorlarni boshqarish** — Ro'yxatdan o'tkazish, qidirish, kontakt va tarix bilan kartochkalar
- **Shifokor paneli** — Shifokor o'z qabullarini ko'radi, jadvalni boshqaradi
- **Yozuv va ro'yxatdan o'tkazish** — Qabullarni rasmiylashtirish, shifokor va xizmatlarni tayinlash, kvitansiya raqamlari
- **Xizmatlar katalogi** — Xizmatlarni, yo'nalishlarni (UZI, EKG va boshqalar), guruhlarni narx va komissiya bilan boshqarish
- **Tahlil natijalari** — Tahlil shablonlari (HTML), natijalarni to'ldirish, Telegram orqali bemorlarga yuborish
- **Kvitansiya tizimi** — PDF kvitansiyalar generatsiya qilish, Telegram bot orqali yuborish
- **Moliyaviy hisobotlar** — Kunlik, haftalik, oylik hisobotlar shifokorlar bo'yicha
- **Telegram bot** — Bemorlar kvitansiya va tahlil oladi; shifokorlar — yozuvlar haqida bildirishnoma; rahbar — kunlik hisobotlar va zaxira nusxalar
- **Rolga asoslangan kirish** — Admin, Kassir, Shifokor turli huquqlar bilan
- **PWA** — Mobil qurilmalarda Progressive Web App sifatida ishlaydi
- **Qorong'u rejim** — Yorug'/qorong'u mavzuni almashtirish
- **Audit** — Barcha foydalanuvchilar harakatlari jurnali
- **Zaxira** — SQL zaxira nusxalarini admin panel yoki Telegram botdan yaratish va yuklab olish

### Texnologiyalar

- **Backend:** PHP 8+ PDO (MySQL) bilan
- **Frontend:** Bootstrap 5, Vue.js, Font Awesome
- **Ma'lumotlar bazasi:** MySQL 8.0
- **Bot:** Telegram Bot API (webhook)
- **PDF:** SimplePdf kutubxonasi

### Muallif

**Sherzod Islomov** — Kasansay, O'zbekiston dasturchisi

---

## 📁 Project Structure

```
lifemed/
├── admin/                  # Admin panel pages
├── api/                    # REST API endpoints + Telegram bot
├── assets/                 # CSS, JS, images, fonts
├── config/                 # Database & app configuration
├── database/               # SQL dumps & migrations
├── includes/               # Auth, helpers, header/footer
├── index.php               # Entry point → redirect to login
├── manifest.json           # PWA manifest
└── sw.js                   # Service worker
```

## 🚀 Getting Started

1. Clone the repository
2. Import `database/lifemed (1).sql` into MySQL
3. Run migrations from `admin/migrate.php`
4. Configure `config/config.php` with your database credentials
5. Set up the Telegram bot via `admin/telegram_settings.php`
6. Open in browser and login (default admin credentials in SQL dump)

## 📄 License

MIT License — feel free to use and modify.

# Shelfwise

**A web-based Library Circulation System built with PHP and MySQL.**

Shelfwise replaces manual, paper-based or spreadsheet-driven library workflows with a single live source of truth. It tracks every book, loan, renewal, reservation and due date in real time — so copy counts are always accurate, waitlists stay fair, and members are warned before a book goes overdue.

> *One desk for every loan, renewal and return.*

---

## Features

- **Live availability** — copy meters and stock counters update the instant a book is borrowed or returned.
- **Borrowing** — members borrow up to 5 books at a time, each with an automatic 14-day due date.
- **Renewal** — extend a loan by 7 days, up to twice, blocked when overdue or reserved by another member.
- **Check-in** — staff return any copy from the desk; members return their own. The first person in the waitlist is notified instantly.
- **Reservations** — fair first-come-first-served waitlists with automatic position tracking.
- **Due-date alerts** — overdue and due-soon warnings at the top of every page.
- **Borrowing history** — a full audit trail of who did what, when, on which book.
- **Undo** — the most recent action can be reversed from the sidebar.
- **Responsive** — the same circulation desk works on desktop, tablet and phone.

## Roles

| Role | Capabilities |
|------|-------------|
| **Member** | Browse, borrow, renew, reserve, return their own books, view their own history. |
| **Staff** | Everything a Member has, plus the Check-In Desk: return any copy, see all active loans. |
| **Administrator** | Everything Staff has, plus catalog management and user-role management. |

## Tech Stack

- **Backend:** PHP 8+ (no framework, no build step)
- **Database:** MySQL / MariaDB
- **Frontend:** Plain HTML + CSS (no JavaScript required)
- **Fonts:** Fraunces (headings) and Inter (body) via Google Fonts

## Requirements

- PHP 8.0 or newer with `pdo_mysql`
- MySQL 5.7+ or MariaDB 10.3+
- A web server (Apache, Nginx, or PHP's built-in server)

---

## Installation

### 1. Clone or copy the project

Place the project folder under your web root. Example for XAMPP:

# Casual HR

Phase 1 of Living School casual HR: the person list, the clear-to-teach light, a single-use invite, and the director contact card.

Laravel and Postgres are the system of record. The casual record does not live in the Next.js app in this repository, in Strapi, or in WordPress.

## Local sign-in is not production login

The sign-in form is a **local stand-in for the Staff Portal Google session**, so the app can be exercised without school credentials.

Production login stays the Staff Portal (`Continue with Google` on the portal). This app does not ship school OAuth client secrets. When someone has a school Google account, HR stores that account’s **subject** (`sub`) on the person. The subject is an identifier, not a secret.

This repository does not change the live portal and does not turn off `https://portal.living.school/login-2/`. WordPress stays up. My programs, incidents, growth plans, and the other portal desks are not migrated here. A WordPress desk stays until a replacement has run for a real week. That cutover is not this phase.

## Where this runs

The destination is Laravel, DigitalOcean Managed Postgres, and DigitalOcean Spaces, on the existing DigitalOcean account, region **syd1**.

Locally, Postgres on this machine stands in for Managed Postgres. Uploads stay on the local disk. The `spaces` disk in `config/filesystems.php` is the syd1 Spaces endpoint (`https://syd1.digitaloceanspaces.com`) for when a file is stored. Phase 1 does not upload contracts or evidence files.

Strapi is not the platform. The Next.js program builder in the parent of this directory is not the builder teachers use. Teachers use WordPress (`my programs`, `ls-tp/v1/programs`). If a booking stores a program id, that id is a **WordPress program id**. This app does not open or copy the program.

## Tenancy

Every school record has `school_id`, including people, accreditation, WWCC, bookings, invites, audits, staff users, and sessions. A second school is another row, not a fork. Cache, queue, and migration tables are framework plumbing shared by the one app.

Dates use **Australia/Sydney**. The seeded school clock is Australia/Sydney.

## The light

- **Green.** Accreditation checked and active, WWCC verified and inside expiry, engagement signed, day-one induction complete, in the casual pool, no amber flag.
- **Amber.** A later induction module is still inside its window, or a PD conversation is due. Office can confirm the booking only when a Conductor note is already on that booking. Amber is never used for WWCC or accreditation.
- **Red.** Not booked. Unverified, expired, barred, or APP-number WWCC; accreditation missing or not active; eTAMS not checked; Conductor alignment not approved (unless another NESA employer arrangement is recorded); engagement unsigned; day-one incomplete. Nobody overrides red.

A Director sees name, mobile, email, stages, today’s booking, the light, and the mentor’s name. They do not see the WWCC number, date of birth, address, pay, or contract text. Office uses the same contact card to build cover and does not open the HR file.

## Run it

Postgres 16 and PHP 8.3 are enough.

```bash
sudo -u postgres psql -c "CREATE ROLE casual_hr LOGIN PASSWORD 'casual_hr';"
sudo -u postgres psql -c "CREATE DATABASE casual_hr OWNER casual_hr;"
sudo -u postgres psql -c "CREATE DATABASE casual_hr_test OWNER casual_hr;"
sudo -u postgres psql -c "ALTER DATABASE casual_hr SET timezone TO 'Australia/Sydney';"
sudo -u postgres psql -c "ALTER DATABASE casual_hr_test SET timezone TO 'Australia/Sydney';"

cd casual-hr
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan test
php artisan serve
```

Open `http://127.0.0.1:8000`. The seeded password for every local account is `local-staff`.

Pages load `public/casual.css` directly. This directory has no `package.json` and no Vite build, so it is not a second frontend for the root Next.js app. Do not deploy Casual HR on Vercel.

| Email | Role |
| --- | --- |
| morgan.hale@example.test | Conductor |
| rowan.ellis@example.test | HR |
| quinn.adler@example.test | Director, Fig Neighbourhood |
| sage.bennett@example.test | Director, River Neighbourhood |
| taylor.brooks@example.test | Office |

The people on the list are fictional. The WWCC numbers are fictional. Do not replace them with real staff or real clearance numbers.

HR can issue an invite from a person’s file. The link works once, expires after seven days, and opens only that person’s induction shell.

Phases 2–6 (induction modules, contract templates, performance, the casual login, expiry jobs) are not built. The person, accreditation, WWCC, and audit tables already have the columns those phases write, so they should not need to be reopened.

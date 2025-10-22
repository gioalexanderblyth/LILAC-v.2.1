# LILAC Pages

This folder contains all the HTML pages for the LILAC application.

## Page Structure

### Main Application Pages
- **`index.html`** - Login/authentication page
- **`dashboard.html`** - Main dashboard (home page after login)
- **`awards.html`** - Awards management page
- **`documents.html`** - Documents management page
- **`events-activities.html`** - Events & Activities page
- **`mou-moa.html`** - MOUs & MOAs page
- **`profile.html`** - User profile page
- **`scheduler.html`** - Scheduler page

### Award Detail Pages
- **`awards/`** - Individual award detail pages
  - `best-asean-awareness-award.html`
  - `best-ched-regional-office-award.html`
  - `emerging-leadership-award.html`
  - `global-citizenship-award.html`
  - `internationalization-leadership-award.html`
  - `most-promising-iro-community-award.html`
  - `outstanding-international-education-award.html`
  - `sustainability-award.html`

## Navigation

All pages include consistent navigation with links to other pages. Since all pages are in the same directory, relative links work correctly between pages.

## Access URLs

When running the server, access pages using:
- **Dashboard**: `http://localhost/pages/dashboard.html`
- **Awards**: `http://localhost/pages/awards.html`
- **Documents**: `http://localhost/pages/documents.html`
- **Events**: `http://localhost/pages/events-activities.html`
- **MOUs & MOAs**: `http://localhost/pages/mou-moa.html`
- **Profile**: `http://localhost/pages/profile.html`
- **Scheduler**: `http://localhost/pages/scheduler.html`

## Design Consistency

All pages follow the same design pattern established in the dashboard:
- Consistent navigation bar with gradient background
- Live date display with calendar icons
- Fixed positioning (`fixed top-0 left-0 right-0 z-[60]`)
- Background gradients (`bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50`)
- Proper spacing (`pt-20`) to account for fixed navigation
- Consistent branding with "LILAC [Page Name]" format

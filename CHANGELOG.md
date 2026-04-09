# Changelog

All notable changes to Fronote will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [2.0.0] "Nova" — 2026-04-09

### Added — 13 New Modules

#### Phase 2 — Portails & Enquêtes
- **portail_parents/** — Consolidated child view, e-signature, QR exit authorizations, ICS calendar, payment history
- **enquetes/** — Multi-page survey builder, anonymous participation, NPS calculation, climate barometer, year-over-year comparison

#### Phase 3 — Scolaire & Sécurité
- **tutorat/** — Algorithmic peer matching (quartile-based), session planning, XP/badges gamification, leaderboard, attestation data
- **intelligence/** — Weighted risk scoring (absences 30% + notes 35% + discipline 20% + engagement 15%), RAG dashboard, pattern detection, auto-recommendations
- **securite/** — PPMS plans, evacuation drills with zone check, hazard registry, emergency alerts, Vigipirate levels
- **accessibilite/** — Accommodations registry, AESH management with calendar, MDPH decisions, ESS planning, RGAA audit

#### Phase 4 — Formation & Logistique
- **formations/** — Training catalog, enrollment workflow, certifications with expiry alerts, budget management, post-training evaluations
- **bourses/** — Eligibility simulator (French national brackets), online applications, instruction workflow, payment scheduling, accounting export
- **inventaire/** — IT asset registry, QR codes, preventive maintenance, loan/return system, depreciation calculation (linear/degressive)
- **echanges/** — Exchange programs (Erasmus+/eTwinning), student applications, host families, CEFR linguistic tracking
- **mediatheque/** — Digital content library, playlists, viewing tracking, ratings/favorites, recommendations, storage quota

#### Phase 5 — New module manifests
- Each new module includes `module.json` manifest with key, category, icon, settings, routes, permissions

### Enhanced — 47 Existing Modules (~200 new features)

#### Pedagogy Modules
- **notes/** — CSV import, configurable weighting by evaluation type, subject-level locking
- **competences/** — Bulk evaluation, cross-reference notes suggestion, LSU export, Cycle 3/4 referentials (D1-D5)
- **devoirs/** — Shingle-based plagiarism detection (Jaccard similarity), peer review, criteria grids
- **cahierdetextes/** — Reusable course templates, read tracking, curriculum alignment, voice notes
- **besoins/** — Multi-stakeholder observations, progress visualization, plan templates, expiry alerts
- **orientation/** — Parcoursup integration (voeux/statuts), interest questionnaire (6 domains), alumni tracking, interview scheduling
- **examens/** — Auto seating plans (alpha/random/alternate), bulk convocations, anonymous copy numbering, CSV result import
- **parcours_educatifs/** — Portfolio generation, bulk validation, photo attachments, progression tracking
- **projets_pedagogiques/** — Budget tracking, Gantt data, parental authorization workflow, project evaluation
- **ressources/** — Versioning, resource sharing, usage statistics, tag-based search
- **bulletins/** — PDF template system, digital signature workflow, parent acknowledgment, class distribution, bulk queue
- **emploi_du_temps/** — Conflict detection, free slot finder, week types (A/B), ICS export, modification notifications

#### Vie Scolaire & Communication
- **absences/** — Pattern detection (by day/by subject), cumulative hours, heatmap data, class comparison
- **appel/** — QR course attendance, precise late recording, period presence export
- **discipline/** — Incident escalation, behavior contracts, academy statistics export
- **vie_scolaire/** — Daily briefing, quick student sheet, cross-module timeline, active alerts
- **signalements/** — Follow-up tracking, assignment, recurrence detection
- **annonces/** — Read acknowledgment, analytics
- **documents/** — Versioning, folder hierarchy
- **reunions/** — Video conference URL, attendance recording, minutes, ICS export
- **trombinoscope/** — Search, trombinoscope data generator, badge data generator
- **support/** — SLA tracking, template responses, satisfaction ratings, FAQ suggestions, internal notes
- **reporting/** — Scheduled reports, KPI tracking, custom SQL report builder

#### Établissement & Logistique
- **bibliotheque/** — ISBN lookup, reading lists, student reader history and stats
- **inscriptions/** — Public portal form, document checklist completion, auto class suggestion, admission letter data, re-enrollment campaigns
- **diplomes/** — QR-verifiable digital diplomas, bulk generation, official register, download tracking
- **stages/** — Convention PDF data, marketplace (offres), visit planning
- **transports/** — GPS stops map (GeoJSON), bus presence tracking, pickup authorizations
- **cantine/** — Nutritional info, menu satisfaction surveys, waste tracking, pre-ordering
- **garderie/** — Real-time present count, activity planning, parent departure notification, monthly summary
- **periscolaire/** — Illustrated catalog, automatic monthly billing, monthly report
- **salles/** — Interactive floor plan, availability calendar, maintenance reports, QR codes, recurring reservations
- **internat/** — Room inspections (cleanliness/order/equipment scoring), evening roll call, exit permissions, weekend activities
- **clubs/** — Session attendance, club budget, photo gallery, waiting list with auto-promotion
- **infirmerie/** — Medication tracking with PAI, epidemic detection, PAI display, monthly stats widget

#### Admin & Navigation
- **parametres/** — Keyboard shortcuts, active sessions management, settings export/import
- **notifications/** — Scheduled notifications, group/class notifications, analytics
- **archivage/** — Scheduled archiving, inter-annual comparison, integrity verification
- **facturation/** — Credit notes (avoirs), treasury dashboard, installment plans (échéancier)
- **personnel/** — Overtime tracking, annual evaluations, leave balance
- **rgpd/** — Processing register (Art. 30), impact analysis (DPIA), data breach management (Art. 33/34), compliance dashboard
- **vie_associative/** — Electronic voting (majority), online membership campaigns, annual report generator
- **agenda/** — Full ICS export, event reminders, agenda statistics, event duplication

### Changed
- Version bumped from 1.0.0 to 2.0.0 "Nova"
- Module count: 47 → 60
- Table count: 156+ → 200+

---

## [1.5.0] "Production" — 2026-04-06

### Added

#### Module Enhancements — Pedagogy (Batch A)
- **notes/** — Batch entry with auto-save, grade locking (`locked_at`/`locked_by`), weighted average calculation, grade distribution statistics, parent notification on new grades
- **competences/** — Configurable referential system, radar graph data, LSU XML export format, link grades to competence evaluations
- **bulletins/** — Live preview, batch generation, appreciation progress tracking per class, customizable PDF templates
- **devoirs/** — Online submission with file upload, late submission tracking (`is_late`), auto-reminders (24h/1h before deadline), teacher annotation, submission dashboard
- **cahierdetextes/** — Rich text entries, multi-file attachments, weekly navigation, copy entry to another class
- **emploi_du_temps/** — Drag-drop schedule editor, conflict detection (room/teacher/class), replacement management with notifications, iCal export
- **examens/** — Exam planning with room assignment, PDF convocations with QR codes, surveillance scheduling
- **agenda/** — Event recurrence (rrule), conflict detection, iCal export, multi-view (day/week/month)

#### Module Enhancements — Student Life & Communication (Batch B)
- **absences/** — Grouped entry, QR presence scanning, online justification upload workflow, SMS alerts, pattern detection (recurring absences)
- **appel/** — Real-time attendance status, history timeline per student, default-present mode
- **discipline/** — Points system with automatic sanction thresholds, discipline timeline, PDF reports
- **vie_scolaire/** — Consolidated dashboard, dropout detection algorithm (absenteeism + grades + incidents scoring)
- **reporting/** — Custom report builder with saved templates, scheduled execution (cron), multi-format export
- **signalements/** — Anonymous reporting with tracking tokens, auto-notification to administration
- **messagerie/** — Already complete: threads, reactions, search, file attachments, WebSocket typing indicators
- **notifications/** — Digest mode (grouped daily emails), bulk operations, filtered listing, notification preferences
- **annonces/** — Already complete: scheduled publishing, read receipts, polls
- **reunions/** — Auto-reminders (24h before), video conference link integration, available slot booking, meeting notes (PV)
- **documents/** — File versioning with history and restore, sharing with role/class targeting

#### Module Enhancements — School & Logistics (Batch C)
- **inscriptions/** — Multi-step form with progress persistence, waitlist management with automatic promotion
- **facturation/** — Auto-billing by service type (cantine/garderie), escalating payment reminders (J+15/J+30/J+45), accounting export
- **stages/** — Weekly journal entries, external evaluation via unique tokens, enterprise directory
- **transports/** — Bus delay signaling with parent notification via push
- **salles/** — Equipment tracking per room (JSON), search rooms by equipment, weekly occupation planning, occupancy rate statistics
- **cantine/** — Allergen conflict detection (cross-reference menu/student allergies), frequentation forecast, 14 EU standard allergens
- **garderie/** — Arrival/departure time tracking, billable hours calculation per month
- **periscolaire/** — Waitlist system with automatic promotion when spots open
- **bibliotheque/** — Book reservation queue with notification when available
- **clubs/** — Session calendar, session management per club, student session view
- **infirmerie/** — Vaccination tracking (7 mandatory vaccines), missing vaccine detection, emergency protocols, frequent visitor tracking, top motifs statistics, monthly statistics
- **trombinoscope/** — RGPD photo consent tracking, consent-filtered class views
- **diplomes/** — Success rate statistics by type/year, mention distribution analysis
- **personnel/** — Leave management workflow (request → approval → auto-create absence), schedule conflict detection, searchable directory
- **ressources/** — Resource sharing with targets (class/role/all), download counter, top downloads
- **internat/** — Evening/morning attendance tracking

#### Module Enhancements — System & Meta (Batch D)
- **support/** — SLA tracking with priority-based targets (urgente: 1h/4h, haute: 4h/24h, normale: 24h/72h, basse: 48h/168h), SLA dashboard metrics, first response recording
- **besoins/** — Periodic evaluation system (JSON), plans needing evaluation detection (>3 months threshold)
- **orientation/** — Career catalog (fiches métiers by sector), counselor appointment booking, orientation history across years
- **parcours_educatifs/** — Student portfolio with file/link attachments, teacher validation workflow
- **projets_pedagogiques/** — Budget tracking with expense recording, budget summary (planned/spent/remaining), kanban board view
- **vie_associative/** — Budget summary (recettes/dépenses/solde), upcoming events across associations, association statistics
- **accueil/** — Already complete: drag-drop widgets, role-based defaults, layout save/load, widget cache
- **archivage/** — Student dossier transfer export (notes, absences, bulletins, health records as JSON)
- **parametres/** — Privacy level setting (public/private profiles)
- **rgpd/** — Already complete: data export, anonymization, consent tracking, retention policies

### Changed
- `version.json` — version bumped to 1.5.0 "Production"
- `README.md` — updated version badge, expanded documentation cross-reference (16 docs linked)

---

## [1.4.0] "Horizon" — 2026-04-04

### Added

#### Infrastructure
- SQL migration system (`API/Services/MigrationService.php`, `API/Commands/migrate.php`)
- Environment detection (`API/Core/Environment.php`) with dev toolbar
- Maintenance mode with admin UI, IP whitelist, and ETA (`API/Services/MaintenanceService.php`)
- Custom error pages (404, 403, 500, 503) with `API/Core/ErrorHandler.php`
- Health check service with DB latency, disk, cache, SMTP, WebSocket, PHP checks

#### UI & Design System
- 17 PHP UI components (`API/UI/Components.php`): card, table, modal, form_group, tabs, badge, toast, skeleton, dropdown, button, alert, pagination, breadcrumb, avatar, stat_card, empty_state
- CSS utility classes (spacing, flex, text, display) in `assets/css/base.css`
- BEM naming convention across all components (`assets/css/components.css`)
- Design tokens refinement (4px grid, subtle shadows)

#### Internationalization (i18n)
- 8 supported locales: FR, EN, ES, DE, RU, NL, AR, TH
- 384 translation files (48 modules x 8 locales) in `lang/{locale}/modules/`
- RTL support for Arabic (`assets/css/rtl.css`, `[dir="rtl"]` selectors)
- Language selector on login page with flag indicators
- Date/number/currency formatting via `IntlDateFormatter` and `NumberFormatter`
- Admin translation management page (`admin/systeme/translations.php`)

#### Credits System
- `author`, `author_url`, `contributors`, `license` fields in all 47 `module.json` files
- Credits persisted in `modules_config` table
- Credits page (`admin/modules/credits.php`) and About page (`admin/about.php`)

#### Feature Flags
- ~80 granular feature flags covering sub-features across all 47 modules
- Admin UI for flag management (`admin/systeme/feature_flags.php`)
- Toggle switches, search/filter, grouped by module
- Migration for bulk flag insertion

#### WebSocket Security
- WSS/TLS support with configurable cert/key paths
- JWT-based authentication with 20-minute token rotation
- Heartbeat mechanism (30s ping, 90s timeout)
- Rate limiting: 30 events/min per connection
- Room membership verification via HTTP callback
- Admin live dashboard (`admin/systeme/live.php`)

#### Marketplace Security
- SHA-256 integrity verification for downloaded packages
- Static analysis scanner (`API/Security/ModuleScanner.php`)
- Blocked dangerous functions: `eval`, `exec`, `system`, `shell_exec`, `passthru`, `proc_open`, `popen`
- Quarantine system for suspicious modules (`API/Services/QuarantineService.php`)
- Automatic backup before module installation with rollback support
- Module permission system (`required_permissions`, `optional_permissions`)

#### AJAX Framework
- Client-side utility (`assets/js/fronote-ajax.js`): post, get, delete, submitForm, confirmDelete, upload
- Server-side response class (`API/Core/AjaxResponse.php`): success, error, redirect, paginated, guard

#### Monitoring & Maintenance
- System monitoring dashboard (`admin/systeme/monitoring.php`)
- Daily maintenance cron: audit cleanup, DB backup, rotation, cache GC, token purge, rate limit cleanup, temp files, sessions, notifications, orphan uploads, translation coverage report
- Hourly maintenance cron: cache GC, health check refresh, disk space check, rate limit cleanup

#### Documentation
- `CONTRIBUTING.md` — contributor guide with setup, code style, architecture, PR process
- `SECURITY.md` — security policy with vulnerability reporting and measures
- `CODE_OF_CONDUCT.md` — community standards
- `CHANGELOG.md` — this file
- GitHub issue templates (bug report, feature request) and PR template
- Technical docs: theme development, translation guide, deployment guide

### Changed
- `docs/module-sdk.md` — added credits, settings schema, AJAX, UI components sections
- `docs/security.md` — added marketplace scanning, module permissions, WebSocket security
- `README.md` — added i18n badge, contributing/security links, feature flags mention
- `templates/shared_header.php` — loads `fronote-ajax.js` globally
- `login/index.php` — all strings use `__()`, language selector, RTL support

---

## [1.3.0] — 2026-03-01

### Added
- Initial 47-module architecture
- IoC Container with service providers
- RBAC with 6 user types
- WebSocket server (Socket.IO) with global client
- Design system with CSS tokens and themes (classic/glass)
- Marketplace for module distribution
- Dashboard with drag-and-drop widgets

---

## [1.2.0] — 2026-01-15

### Added
- Audit logging system
- Rate limiting with exponential backoff
- File upload service with context-based validation
- Import/Export service for users and configuration

---

## [1.1.0] — 2025-11-01

### Added
- API token authentication (Bearer tokens)
- Module settings schema system
- Notification center with multi-channel support

---

## [1.0.0] — 2025-09-01

### Added
- Initial release of Fronote
- Core modules: accueil, notes, absences, emploi du temps, messagerie
- Session-based authentication
- MySQL/MariaDB database with PDO
- Apache with mod_rewrite routing

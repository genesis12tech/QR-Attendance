# User Stories — Secure Dynamic QR Attendance System

Organized by epic. Format: `As a [role], I want [feature], so that [benefit].`

---

## Epic 1: Platform Configuration (Super Admin)

**US-001 — Security Policy Management**
As a super admin, I want to configure QR expiry time, risk thresholds, geofence radius, device binding, and clock skew tolerance in a single settings form, so that the platform's fraud-detection behaviour matches our institutional policy without touching code.

**US-002 — Data Retention Policy**
As a super admin, I want to define and activate a data retention policy that automatically purges old records, so that we remain compliant with data minimisation requirements.

**US-003 — System Settings Editor**
As a super admin, I want to edit key-value system settings (e.g. `faculty_can_review_flags`, queue concurrency), so that I can fine-tune behaviour for each deployment environment.

**US-004 — Policy Change Audit**
As a super admin, I want every security policy save to be logged in the audit trail with old and new values, so that I can prove compliance during external audits.

---

## Epic 2: User & Department Administration (Super Admin)

**US-005 — Department CRUD**
As a super admin, I want to create, edit, and soft-delete departments and assign a head faculty member to each, so that the platform mirrors the institution's academic structure.

**US-006 — Admin Account Management**
As a super admin, I want to create admin accounts, assign them to departments, and suspend or revoke their access, so that I retain full control over who manages each department.

**US-007 — Role Assignment**
As a super admin, I want to assign and revoke roles (admin, faculty) across the platform, so that access rights always reflect current staffing.

---

## Epic 3: Audit & Compliance (Super Admin)

**US-008 — Audit Log Browsing**
As a super admin, I want to browse a read-only audit log filtered by actor, action type, entity, and date range, so that I can investigate any suspicious activity.

**US-009 — Audit Log Export**
As a super admin, I want to export the audit log to CSV, so that I can share evidence with compliance officers or legal teams.

**US-010 — System Health Dashboard**
As a super admin, I want a dashboard widget that shows active security policy settings, Redis connectivity, queue worker status, and the last data-retention run, so that I can confirm the platform is operating correctly at a glance.

---

## Epic 4: Academic Entity Management (Admin)

**US-011 — Student Management**
As an admin, I want to create, edit, and filter student records scoped to my department, so that student data stays accurate without exposing data from other departments.

**US-012 — Faculty Management**
As an admin, I want to manage faculty profiles and designations within my department, so that course assignments and timetables remain up to date.

**US-013 — Course Configuration**
As an admin, I want to create courses with a minimum attendance percentage, so that the system can automatically identify defaulters.

**US-014 — Room & Geofence Setup**
As an admin, I want to register rooms with GPS coordinates, geofence radius, and optional beacon/Wi-Fi identifiers, so that the system can verify a student's physical presence during attendance.

**US-015 — Timetable Management**
As an admin, I want to create and maintain timetable entries linking a course, class group, faculty, room, and recurring time slot, so that faculty can start sessions directly from their schedule.

---

## Epic 5: Enrollment Management (Admin)

**US-016 — Individual Enrollment**
As an admin, I want to enroll a student into a course and class group with an effective date, so that their attendance is tracked from the correct date.

**US-017 — Bulk Enrollment**
As an admin, I want to select multiple students and enroll them in a course in one action, so that I can onboard a whole cohort at the start of semester without repetitive data entry.

**US-018 — Enrollment Status Management**
As an admin, I want to bulk-drop or mark enrollments as completed, so that the active roster stays clean after course completion or withdrawals.

---

## Epic 6: Proxy Flag Review (Admin)

**US-019 — Flag Review Queue**
As an admin, I want to see a prioritised queue of proxy flags sorted by severity and risk score, so that the most suspicious attendance records are reviewed first.

**US-020 — Evidence Inspection**
As an admin, I want to open a flag and view the student's GPS coordinates, device fingerprint, and risk breakdown in a modal, so that I have full evidence before making a decision.

**US-021 — Approve / Reject with Notes**
As an admin, I want to approve or reject a flag with a mandatory reviewer note on rejection, so that decisions are documented and defensible.

**US-022 — Bulk Flag Actions**
As an admin, I want to select multiple flags and approve or reject them in one action with an optional bulk note, so that I can clear a backlog efficiently.

---

## Epic 7: Attendance Oversight & Overrides (Admin)

**US-023 — Attendance Record Review**
As an admin, I want to view attendance records filtered by status, course, risk level, and date, so that I can investigate attendance patterns across the department.

**US-024 — Manual Attendance Override**
As an admin, I want to change an attendance status with a mandatory reason (minimum 20 characters), so that legitimate corrections are possible while preventing casual edits.

**US-025 — Override Audit Trail**
As an admin, I want every override to record the old status, new status, reason, and overriding officer in the audit log, so that changes are fully traceable.

---

## Epic 8: Reporting (Admin)

**US-026 — Attendance Report Generation**
As an admin, I want to generate attendance reports scoped by department, course, faculty, or student over a date range and export them as PDF, CSV, or XLSX, so that I can submit accurate attendance data to management.

**US-027 — Defaulter List**
As an admin, I want to view a live defaulter list showing each student's attendance percentage against the course minimum, so that I can identify students at academic risk early.

**US-028 — Absence Notifications**
As an admin, I want to send automated absence notifications to selected defaulters in one action, so that students are alerted before formal consequences apply.

---

## Epic 9: Session Lifecycle (Faculty)

**US-029 — Start Session from Timetable**
As a faculty member, I want to start an attendance session directly from today's timetable slot, so that sessions are always linked to the correct course, group, and room.

**US-030 — QR Display Page**
As a faculty member, I want a full-screen QR display page I can project in class that shows a countdown timer, live attendance counters, and auto-refreshes the QR every 30 seconds, so that students can scan without interruption and I can monitor progress in real time.

**US-031 — Close Session**
As a faculty member, I want to close the active session from the QR display page or session list with an optional close reason, so that no further scans are accepted after class ends.

**US-032 — Reopen Session**
As a faculty member, I want to reopen a recently closed session within a configurable grace window, so that I can recover from an accidental early closure.

**US-033 — Force QR Refresh**
As a faculty member, I want to manually regenerate the QR immediately, so that I can invalidate a compromised code without waiting for the 30-second rotation.

**US-034 — Pause Session**
As a faculty member, I want to pause a session to suspend QR rotation (e.g. during a break), so that no new codes are accepted while attendance is paused.

---

## Epic 10: Real-Time Monitoring (Faculty)

**US-035 — Live Attendance Feed**
As a faculty member, I want a live feed of the last 10 scans showing student name, status, and risk score refreshed every 3 seconds, so that I can spot suspicious activity as it happens.

**US-036 — Proxy Flag Alerts**
As a faculty member, I want flagged scans shown prominently on the QR page with Allow/Deny quick-action buttons (when policy permits), so that I can resolve high-risk scans during class.

**US-037 — Session Export**
As a faculty member, I want to export a session summary as PDF, CSV, or XLSX from the QR page or session list, so that I have a record for my own files.

---

## Epic 11: Student Attendance Scanning (Mobile App)

**US-038 — QR Scan & Attendance Marking**
As a student, I want to scan the QR code displayed in class using the mobile app, so that my attendance is recorded instantly without filling in a paper register.

**US-039 — Scan Validation Feedback**
As a student, I want to receive immediate feedback (confirmed, late, or rejected) after scanning, so that I know whether my attendance was successfully recorded.

**US-040 — Device Binding**
As a student, I want my attendance to be tied to my registered device, so that another student cannot mark my attendance from their phone.

**US-041 — Geofence & Location Check**
As a student, I want the app to verify I am within the classroom geofence before submitting a scan, so that remote or off-campus scans are automatically rejected.

**US-042 — Attendance History**
As a student, I want to view my attendance record per course — including present, late, and absent entries — and see my current percentage against the minimum required, so that I can track my standing and avoid becoming a defaulter.

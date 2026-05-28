# EMBA Admission Flow

The process runs between two actors: the **Candidate** and the **Admin**.

> **Note:** Everything is isolated per batch. All data, lists, calculations, and operations described below are scoped to a single batch and do not carry over or mix across batches.
>
> Batch-level parameters are **dynamic and controllable by the Admin per batch**, including (but not limited to) the pass mark, all admission-related fees (application fee, subject choice fee, admission fee), the MCQ threshold / eligible-for-viva value, and per-subject allocation limits.

## 1. Admission Starts

- **Admin** creates a batch and opens the session for registration with a time limit for application intake & payment.
- **Candidate** creates an account and verifies it via OTP (mobile/email).
- **Candidate** applies. The system records all necessary data related to the application.

### Marks Distribution

| Component      | Marks   |
| -------------- | ------- |
| MCQ            | 55      |
| Written        | 25      |
| Schooling Year | 5       |
| Experience     | 10      |
| Viva           | 5       |
| **Total**      | **100** |

**Pass Mark:** 40 _(default; admin-configurable per batch)_

> **Important Note**
>
> - **Schooling Year** and **Experience** values are taken directly from the application form data.
> - **Experience:** the maximum mark for this metric is 10. A candidate's first two years of experience do not count (counted as zero); thereafter every consecutive year adds one point, reaching the max of 10 at an effective 12 years of experience. Experience beyond 12 years does not count.

## 2. Payment & Roll Number

- **Candidate** pays the application fee. Whoever pays gets a roll number generated automatically.
- **Admin** checks candidates for paid/unpaid status.
- **Admin** follows up with SMS/Email to unpaid candidates to fulfill payment.
- **Candidates** pay.
- **Admin** has the final list of admission intakers.

**APPLICATION & PAYMENT INTAKE CLOSED**

## 3. Seat Planning & Admit Card

- **Admin** takes the intaker list with roll numbers and prepares the seat plan (offline).
- **Admin** uploads a CSV file with the seat plan.
- **System** matches the seat planning with intakers.
- Admit card is generated.
- **Candidates** view/download their admit card with exam roll number & room number.
- **Admin** prepares the attendance sheet (system generated).

## 4. Exam

- **Admin** uploads a CSV file with MCQ marks for intakers.
- **System** has a predefined dynamic 'value' to determine who to call for the viva exam. It matches the MCQ marks against that value and provides the list of intakers eligible for viva. A candidate is eligible to sit for viva if their MCQ mark is **≥ 25** _(default; admin-configurable per batch)_.
- **System** already has Schooling Year and Experience points stored.
- **Admin** uploads the CSV file containing written exam marks.
- **Admin** uploads the 'Viva Boards' list with board name and the range of intakers each board will examine.
- **Admin** downloads the board sheet.
- **Admin** sets the Viva Date notice.
- **Intakers** see the viva exam notification on their portal, including who is eligible to sit for viva.

## 5. Viva Exam

- **Admin** re-evaluates schooling year & experience, adjusting points if required on the printed board sheet/Excel file, and assigns viva marks out of 5 on the same sheet.
- **Admin** uploads a sheet storing viva points, adjusted schooling points, and adjusted experience points.
- **System** calculates the subtotal marks for all intakers who took the viva exam and prepares the merit list.

## 6. Subject Choice & Allocation

- **Admin** sets the max allocation of students per subject, either by uploading a CSV or directly in the admin panel.
- **Admin** announces the merit-listed intakers to choose their subjects.
- **Intaker** pays the subject choice fee (500 BDT) _(default; admin-configurable per batch)_.
- Only after paying the fee, the **Intaker** makes subject choices (from 9 subjects), prioritizing from top choice to least, not constrained to choosing all subjects. One can choose all 9, just 1, or any number in between.
- **System** runs a calculation to allocate subjects according to the merit list, from top to bottom.

## 7. Admission Fee

- Selected **Intakers** pay the 12,000 BDT admission fee from their portal _(default; admin-configurable per batch)_.
- **Admin** enables the intaker to pay the admission fee.
- **Admin** sees the reports of who paid.

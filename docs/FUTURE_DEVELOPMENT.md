# Bubba Hub — Future Development Backlog

This is the working backlog for future Bubba Hub development.

## Workflow

### “create the next future development request”
When this command is used, Chat should:
1. Read this backlog.
2. Find the next suitable OPEN request.
3. Turn it into a clear development request with goal, scope, affected areas, acceptance criteria and dependencies.
4. Present it for user approval/changes.
5. Do not implement anything until the user approves it.
6. After implementation, update the request with status, notes and commit.

### “close X request”
Identify request X and, once the user confirms it is complete, mark it CLOSED and record the completion date and commit.

Partially completed work should remain IN PROGRESS, not closed.

## Statuses
- OPEN — planned
- IN PROGRESS — being worked on
- BLOCKED — waiting on dependency/decision
- ON HOLD — deliberately paused
- CLOSED — completed and accepted

---

# Future Development Requests

## FD-001 — [OPEN] Paid Advertising Zones for Leaders

**Goal:**  
Create a paid advertising system that allows Bubba Hub leaders/organisers to purchase advertising placements on the website for a 30-day advertising period.

**Why:**  
Give leaders an additional paid promotion option while creating a sustainable revenue stream for Bubba Hub.

**Advertising zones:**
1. **Site Wide** — advertisement can appear throughout the site.
2. **My Hub Only** — advertisement appears within the My Hub/family experience.
3. **My Hub & Directory** — advertisement appears in My Hub and directory areas.
4. **Home Page Only** — advertisement appears only on the home page.

**Campaign duration:**
- Standard campaign length: 30 days.
- One-off campaign — runs for 30 days and then ends.
- Recurring campaign — automatically renews for another 30-day period, subject to successful payment and approval rules.

**Advertiser content options:**
- **Existing Bubba Hub listing** — use a selected listing as the advertised content/destination.
- **Leader storefront** — advertise the leader's storefront/organisation instead of a specific activity listing.

The setup should clearly show the chosen zone, content, duration and cost before payment.

**Scope:**
- Add advertising-zone management to admin.
- Add leader-facing advertising purchase and campaign management.
- Define the four placement/display rules.
- Add 30-day campaign lifecycle.
- Add one-off vs recurring billing.
- Allow campaign to use an existing listing or storefront.
- Add campaign status handling.
- Integrate with the project's chosen payment provider.
- Add admin approval/control.
- Add basic campaign statistics where practical.
- Automatically stop expired campaigns.
- Handle cancelled/failed recurring payments safely.
- Prevent adverts displaying before required approval/payment conditions are met.

**Likely files/areas:**
- admin/ — advertising management
- api/ — advertising, campaign and payment endpoints
- assets/js/ — leader/admin advertising interfaces
- assets/css/ — advertising UI and placement styling
- database schema/migrations — campaigns, zones, billing and relationships
- leader/account pages — purchase and campaign management
- directory, My Hub and home page rendering — placement
- existing payment/subscription infrastructure — reuse where appropriate

**Acceptance criteria:**
- [ ] Admin can create/manage the four advertising zones.
- [ ] Leader can select one of the four zones.
- [ ] Leader can choose a listing or storefront.
- [ ] Leader sees campaign duration and cost before purchasing.
- [ ] Campaign runs for exactly 30 days from its defined start point.
- [ ] Leader can select one-off or recurring billing.
- [ ] Recurring campaigns renew for another 30 days only after successful renewal/payment.
- [ ] Expired/cancelled campaigns stop displaying automatically.
- [ ] Admin can approve, pause, reject, cancel and manage campaigns.
- [ ] Advertising displays only in the zone purchased.
- [ ] Leader permissions prevent one leader managing another leader's campaigns.
- [ ] Payment and campaign status remain synchronised.
- [ ] Campaign start/end dates and billing state are recorded.
- [ ] Mobile and desktop placements fit the Bubba Hub theme.
- [ ] Basic campaign reporting is available, or limitations are clearly documented.

**Dependencies / questions:**
- Advertising prices for each zone need to be decided.
- Confirm the payment provider and current recurring-payment capability.
- Decide whether recurring campaigns renew automatically or require re-approval.
- Define maximum simultaneous adverts per zone.
- Define advert rotation when multiple leaders purchase the same zone.
- Define whether Site Wide includes account/auth pages or public pages only.
- Define exact storefront content used for storefront campaigns.
- Decide whether leaders upload custom artwork or Bubba Hub generates the advert from listing/storefront data.
- Decide whether approval occurs before payment, after payment, or both.
- Establish refund/cancellation rules.

**Implementation notes:**
- Do not start coding until the user approves this request and key pricing/payment/display decisions are resolved.
- Reuse existing authentication, leader permissions, listing relationships and payment infrastructure where practical.
- Build advertising as a configurable system rather than hard-coded placements.
- Use a date-driven campaign lifecycle so expired advertising does not require manual removal.

**Completion:**
- Status: OPEN
- Completed: —
- Commit: —


## FD-002 — [OPEN] Central Payment System

**Goal:**  
Build a reusable Bubba Hub payment system that can support payments for bookings, advertising campaigns and Pro account subscriptions.

**Scope:**
- Create a central payment abstraction rather than separate payment code for each feature.
- Support payment methods/providers: PayPal, Stripe and bank transfer.
- Provide payment status tracking, transaction records and reconciliation.
- Support one-off and recurring payments where the provider allows it.
- Integrate with bookings, advertising campaigns and Pro accounts.
- Provide admin visibility of transactions and payment states.
- Handle successful, failed, pending, cancelled and refunded payments.
- Store only the payment information necessary for the application; do not store raw card details.

**Acceptance criteria:**
- [ ] A single payment architecture can be reused by bookings, ads and Pro accounts.
- [ ] User can select an available payment method.
- [ ] Stripe payments work end-to-end.
- [ ] PayPal payments work end-to-end.
- [ ] Bank-transfer payments can be recorded and reconciled.
- [ ] Payment status is visible to the relevant user and admin.
- [ ] Failed, cancelled and refunded states are handled.
- [ ] Recurring billing can be supported where required.
- [ ] Payment records can be linked to the relevant booking, campaign or subscription.
- [ ] Admin can review transaction history.
- [ ] Webhook/callback processing is secure and idempotent.

**Dependencies / questions:**
- Confirm Stripe and PayPal account/business setup.
- Decide whether bank transfer is manual confirmation or automated bank-payment integration.
- Define currency, tax/VAT handling and refund rules.
- Define exactly which Pro account tiers are paid through this system.
- Define whether platform fees are separate from provider processing fees.

**Status:** OPEN

---

## FD-003 — [OPEN] Booking Payments and 2% Bubba Hub Booking Fee

**Goal:**  
Create booking payment processing where the leader receives the booking payment directly and Bubba Hub automatically receives a **2% booking fee** from each transaction.

**Payment flow:**
1. User books an activity.
2. User chooses an available payment method: PayPal, Stripe or bank transfer.
3. The booking payment is attributed to the relevant leader.
4. Bubba Hub receives a 2% booking fee per transaction.
5. The booking and payment records are linked.
6. Leader can see booking/payment status and their payable amount.
7. Bubba Hub admin can see transaction, commission and reconciliation information.

**Scope:**
- Leader payment-account/onboarding configuration.
- Booking checkout/payment flow.
- 2% booking-fee calculation.
- Leader payout/payment routing.
- Bubba Hub commission ledger.
- Stripe/PayPal marketplace or connected-account capability where applicable.
- Bank-transfer booking workflow.
- Payment confirmation and webhooks.
- Refund/cancellation handling.
- Failed/pending payment handling.
- Admin reconciliation and reporting.
- Clear customer receipts/payment records.
- Protection against duplicate charges and duplicate webhook processing.

**Acceptance criteria:**
- [ ] Booking checkout calculates the booking total and 2% Bubba Hub booking fee correctly.
- [ ] The 2% fee is recorded as Bubba Hub commission for every eligible transaction.
- [ ] Leader receives the booking proceeds through the supported payment flow.
- [ ] User can choose an enabled payment method.
- [ ] Stripe booking payments work end-to-end.
- [ ] PayPal booking payments work end-to-end where supported by the marketplace/platform integration.
- [ ] Bank-transfer bookings can be recorded and reconciled.
- [ ] Booking status cannot be incorrectly marked paid from an unverified client-side response.
- [ ] Provider webhooks/callbacks are verified and idempotent.
- [ ] Refunds correctly update booking and commission records.
- [ ] Admin can reconcile gross booking amount, Bubba Hub fee, provider fees where available, refunds and leader amount.
- [ ] Leaders can see relevant payment/payout status without accessing another leader's financial data.
- [ ] Customers receive a clear payment/booking confirmation.
- [ ] No raw card/bank credentials are stored by Bubba Hub.
- [ ] The system prevents duplicate payment/commission records.

**Dependencies / questions:**
- **Confirmed:** the 2% Bubba Hub booking fee is paid by the customer, so the leader does not lose money from the platform booking fee.
- Confirm whether the 2% applies to bank-transfer bookings and manually confirmed bookings.
- Provider processing fees still need to be defined separately from the 2% Bubba Hub booking fee.
- **Confirmed for the current project plan:** no VAT is required for the booking fee.
- Confirm the payment provider's marketplace/connect requirements for paying leaders directly.
- Payout timing remains to be defined based on the selected payment provider and leader payment setup.
- **Confirmed:** refunds are handled directly between the customer and the related organiser; Bubba Hub does not manage the refund itself.
- Define whether leaders must complete identity/business verification before accepting online payments.

**Implementation notes:**
- FD-003 should build on FD-002 rather than creating a separate payment system.
- The payment architecture must distinguish customer payment, provider processing fee, Bubba Hub booking fee, leader proceeds and refunds.
- Financial calculations should be server-side and auditable.
- Do not treat a client-side success page as proof of payment.

**Status:** OPEN

---


## FD-004 — [OPEN] Detailed Legal, Privacy, Refund and Policy Pages

**Goal:**  
Create a complete, clearly organised set of public-facing legal and policy pages for Bubba Hub, covering families/users, leaders/organisers, bookings, payments, advertising, accounts and the handling of personal and child-related information.

**Important:**  
These pages should be written as practical website policies and then reviewed by an appropriate UK legal/data-protection professional before being treated as final legal advice. Privacy content should reflect the actual data flows and providers used by the finished application.

**Core pages:**
- Privacy Notice
- Terms & Conditions
- Booking Terms
- Refund & Cancellation Policy
- Payment Terms
- Leader/Organiser Terms
- Pro Account Terms
- Advertising Terms
- Cookie Policy
- Acceptable Use Policy
- Community/Content Guidelines
- Complaints Policy
- Data Retention Policy / retention information
- Data Subject Rights / privacy requests
- Child and family data information
- Consent and permissions information
- Accessibility statement
- Website disclaimer
- Contact/legal information

**Privacy/data coverage should address:**
- Family accounts and profiles
- Children's names, dates of birth, photos and other profile information
- Booking information
- Consent records
- Medical/health information supplied through booking or consent processes
- Leader/organiser information
- Payment and transaction information
- Advertising campaign information
- My Hub and planner data
- Saved activities/preferences
- Notifications, email and future push messaging
- Analytics/cookies
- Third-party services and processors
- Data sharing between Bubba Hub, leaders/organisers and relevant service providers
- International transfers where applicable
- Data retention/deletion
- User rights and privacy requests
- Data breach/contact procedures
- Marketing preferences and withdrawal of consent

**Booking/refund rules to document:**
- Bubba Hub facilitates bookings/payment functionality where applicable.
- The relevant organiser is responsible for the activity/service being booked.
- Refunds are handled directly between the customer and the relevant organiser, according to the organiser's applicable terms.
- Bubba Hub's 2% booking fee should be clearly explained as a customer-paid platform booking fee when the booking-payment system is implemented.
- Payment-provider fees and payment processing arrangements should be explained accurately once the final provider setup is confirmed.

**Advertising terms should cover:**
- Four advertising zones
- 30-day campaigns
- One-off and recurring campaigns
- Listing vs storefront advertising
- Pricing/payment
- Approval/rejection
- Prohibited advertising
- Campaign cancellation
- Expiry
- Recurring renewal
- Advert content responsibility
- Intellectual property
- Misleading claims
- Suspension/removal
- Refund/cancellation rules for advertising

**Leader terms should cover:**
- Listing accuracy
- Safeguarding responsibilities
- Activity/service responsibility
- Venue information
- Booking responsibility
- Customer communication
- Child/family information received by organisers
- Appropriate handling of sensitive information
- Cancellation policies
- Payment/payout obligations
- Advertising
- Pro accounts
- Suspension/removal
- Complaints and disputes

**Acceptance criteria:**
- [ ] All required legal/policy pages exist as separate, clearly accessible pages.
- [ ] Pages are linked from the main/footer navigation where appropriate.
- [ ] Relevant policies are linked contextually during account creation, booking, payment, advertising and leader onboarding.
- [ ] Privacy information reflects actual application data flows rather than generic template wording.
- [ ] Child/family and sensitive information receives appropriate privacy explanations.
- [ ] Booking, payment, refund and organiser responsibilities are clearly separated.
- [ ] Advertising terms cover one-off and recurring 30-day campaigns.
- [ ] Cookie information identifies necessary vs optional cookies/technologies where applicable.
- [ ] Users can find how to make privacy requests and complaints.
- [ ] Policy version/effective-date information is displayed.
- [ ] Policy changes can be tracked and updated without breaking application links.
- [ ] Pages are mobile-friendly and match the Bubba Hub visual design.
- [ ] Legal links are available before relevant consent, booking or payment actions.
- [ ] Final wording is reviewed for consistency with the actual implemented system before launch.

**Likely files/areas:**
- Existing `privacy.html` and `terms.html`
- New legal/policy HTML pages
- Footer/navigation/menu system
- Account registration and consent flows
- Booking/payment flows
- Leader onboarding/account pages
- Advertising purchase flow
- Cookie/consent implementation
- Admin content/navigation management
- Database only where policy versioning/acknowledgement records are required

**Dependencies / questions:**
- Confirm the legal entity/name and contact details that should appear on the policies.
- Confirm whether Bubba Hub will operate as a CIC/company and the exact registered details once available.
- Confirm final payment providers and third-party processors before finalising payment/privacy wording.
- Confirm final data retention periods.
- Confirm cookie/analytics tools actually used.
- Confirm whether leaders are independent service providers and how that relationship should be described.
- Confirm the final booking/refund responsibilities and organiser terms.
- Legal/data-protection review should take place before the pages are treated as final.

**Implementation notes:**
- Do not copy generic legal templates without checking them against Bubba Hub's real features and data flows.
- Keep legal content separate from application logic where practical so policies can be updated easily.
- Include effective date and version information.
- Avoid promising rights, refunds, security measures or processing arrangements that the application does not actually provide.
- Privacy wording should follow current UK guidance and be reviewed when the application's data processing changes.

**Status:** OPEN


# Development History

_No requests have been closed yet._

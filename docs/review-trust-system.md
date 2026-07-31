# LuxurrStay Review and Trust System

## Overview

LuxurrStay's review and trust system is designed to keep marketplace ratings useful, fair, and difficult to manipulate.

The system aims to:

- Allow reviews only from guests with verified stays.
- Prevent fake or unfair ratings from distorting property reputation.
- Avoid misleading public ratings when a property has very few reviews.
- Protect guests and hosts from suspicious review patterns.
- Keep moderation decisions traceable through internal audit logs.

Public users see simple, confidence-aware review information. Internal systems keep additional moderation, risk, and audit data private.

## Review Eligibility

A guest can review a property only when all eligibility rules pass:

- The guest must be authenticated.
- The guest must provide an explicit `booking_id`.
- The booking must belong to the authenticated guest.
- The booking must belong to the reviewed property.
- The booking status must be `accepted` or `completed`.
- The booking `end_date` must be before today.
- The guest cannot review before the stay has ended.
- The property owner cannot review their own property.
- Only one review is allowed per booking.

This keeps reviews tied to real completed stays and prevents owners, unrelated users, or future guests from influencing public ratings.

## Public Rating Display Rules

Public rating display uses only published verified reviews.

- `0` published reviews: show `New`.
- `1` to `4` published verified reviews: show `Rating forming`.
- `5+` published verified reviews: show the real guest `average_rating`.

The internal `ranking_score` is not displayed publicly. It exists for ranking and trust calculations so a property with one perfect review does not unfairly outrank properties with a stronger review history.

## Moderation Statuses

Reviews can have one of these moderation statuses:

- `published`: visible publicly and included in public rating calculations.
- `pending_review`: held for moderation and excluded from public surfaces.
- `rejected`: excluded from public surfaces.

Only published reviews affect:

- Public `average_rating`
- Public `reviews_count`
- Trust badges
- Public property review lists

Pending and rejected reviews remain internal and do not improve or harm public ratings.

## Duplicate and Concurrency Protection

Review creation is hardened against duplicate submissions and repeated attempts:

- One review is allowed per booking.
- Review creation runs inside a database transaction.
- The booking row is locked during review creation where supported.
- The duplicate review check is repeated inside the transaction.
- A unique `booking_id` constraint is the final database-level protection.
- Duplicate attempts return a safe `409 Conflict` response.
- The review creation endpoint is throttled to reduce excessive attempts.

The duplicate response is user-safe and does not expose implementation details.

## Privacy-Safe Risk Signals

The review risk foundation stores internal risk signals for moderation decisions:

- `risk_score`
- `risk_reasons`
- `ip_hash`
- `user_agent_hash`

Privacy rules:

- Raw IP addresses are never stored.
- Raw user agents are never stored.
- Network and browser values are hashed with HMAC.
- Hashes are internal only.
- Risk scores and reasons are never exposed publicly.

Fairness rules:

- A `1` star review alone is not fraud.
- A `5` star review alone is not fraud.
- A shared network alone is not enough to mark a review high risk.
- High-risk reviews become `pending_review`; they are not rejected automatically.

Current risk signals include account age, review bursts, duplicate or highly similar content, shared network clusters, repeated recent review volume, and invalid booking signals.

## Audit Logs

Review moderation events are recorded in the internal `review_moderation_logs` table.

Supported actions:

- `created`
- `auto_published`
- `auto_flagged`
- `moderator_published`
- `moderator_rejected`
- `status_changed`

Audit logs are internal only and are not exposed to public users. Logs are append-only at the application level, helping the team trace why a review was created, auto-published, auto-flagged, or later changed by moderation.

Safe metadata may include:

- `old_status`
- `new_status`
- risk reason codes
- `risk_score`

Audit metadata must not include raw IP addresses, raw user agents, secrets, or full internal threshold configuration.

## Trust Badges

Trust badges are conservative public signals based on published verified review history and unresolved high-risk pending review status.

No badge is shown when:

- The property has fewer than `10` published reviews.
- The property is still `New` or `Rating forming`.
- The average rating is below the required threshold.
- The property has an unresolved high-risk pending review cluster.

### Trusted

Requirements:

- At least `10` published verified reviews.
- `average_rating >= 4.5`.
- No unresolved high-risk pending review.

### Highly Trusted

Requirements:

- At least `20` published verified reviews.
- `average_rating >= 4.7`.
- No unresolved high-risk pending review.

### Top Rated

Requirements:

- At least `50` published verified reviews.
- `average_rating >= 4.8`.
- No unresolved high-risk pending review.

Pending and rejected reviews do not unlock badges. Unresolved high-risk pending reviews block badges until moderation resolves them.

## Current MVP Limitations

Current badges are based only on:

- Published verified review count.
- Published verified review average.
- Existing risk status for unresolved high-risk pending reviews.

The current system does not yet include these future trust requirements:

- Cancellation rate
- Complaints or reports
- Payment verification
- Owner identity verification
- Property verification
- Completed stay tracking beyond the current booking eligibility rules
- Admin moderation dashboard

The system does not claim that trust badges depend on those signals yet.

## Future Phases

Recommended next phases:

1. Cancellation system
2. Complaints and reports system
3. Payment verification
4. Owner verification
5. Property verification
6. Trust Badge v2 using all trust signals
7. Admin moderation dashboard

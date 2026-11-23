# Bubba Hub Product Blueprint

**Status:** Product vision agreed; blueprint v1.0  
**Purpose:** Establish what Bubba Hub is before selecting plugins, planning migration, or writing more custom code.

## 1. Product definition

Bubba Hub is a family discovery, planning and connection platform for pregnancy, babies and young children across Devon and Cornwall.

It brings together local activities, classes, events, venues, services and family support, helping families discover opportunities and organise family life. It gives providers and community organisations a simple way to manage their information, promote what they offer and connect with local families.

**Tagline:** Discover. Plan. Connect. Explore.

**Core promise:** Making family life easier.

## 2. Purpose and audiences

Bubba Hub has two equally important purposes:
- A discovery directory for pregnancy, baby and toddler activities, services and support.
- A family hub where people can save opportunities and organise their family week.

It serves two primary audiences equally:
- **Families:** expectant parents, parents, carers and families with children from pregnancy through early childhood.
- **Providers and leaders:** independent class leaders, activity providers, businesses, venues and organisations.

Community and support organisations are also important contributors and users of the platform.

## 3. Eight core content and service areas

1. **Pregnancy and maternity:** maternity services, antenatal groups and support.
2. **Baby:** baby classes, groups, activities and resources.
3. **Toddler and preschool:** activities and opportunities for young children.
4. **Family and community support:** local services, support groups and advice.
5. **Activities directory:** searchable classes, groups, providers and services.
6. **Events:** one-off events, workshops and recurring sessions.
7. **Family Planner:** save favourites and organise a personal family week.
8. **Bookings and payments:** enquiries, reservations and payments where appropriate.

These are product areas, not necessarily eight separate software systems or databases.

## 4. The two connected experiences

### Family Hub
- Discover and search local opportunities.
- Filter by location, age and interest.
- View activities, providers, venues, sessions and events.
- Save favourites.
- Build a personal family planner.
- Make bookings or enquiries where available.
- Find pregnancy, family and community support.

### Leader Hub
- Create and manage a provider profile.
- Create and maintain activities and listings.
- Reuse venue information.
- Manage schedules, sessions and events.
- Receive and manage enquiries or bookings where supported.
- Keep information accurate without repeatedly entering the same details.

**Key principle:** providers should enter information once and reuse it wherever relevant. A class should not need to be recreated separately for the directory, calendar, event pages and planner.

## 5. Shared information model

The product should distinguish between:

- **Provider / organisation:** who runs the activity or service.
- **Activity / service:** what is offered.
- **Venue:** where it takes place.
- **Session / event:** when it takes place, including individual dates and recurring schedules.
- **Family planner entry:** a family's saved reference to an activity or session.

These records should be connected rather than duplicated. A provider may run multiple activities at multiple venues. The same venue may be used by multiple providers.

The Family Planner should reference the source activity and session records rather than maintaining unnecessary copies. Where a provider updates a session, the family should be able to see the current information while retaining their own planning choices and notes.

## 6. Product principles

1. **Simple for families:** finding something suitable should be quick and intuitive.
2. **Simple for leaders:** enter information once and reuse it.
3. **Local first:** relevant Devon and Cornwall information, with town and regional discovery.
4. **Connected:** directory, events, venues, planner and bookings should work together.
5. **Affordable:** favour reliable free tools and avoid unnecessary subscriptions.
6. **Low maintenance:** use proven plugins where they meet requirements; reserve custom code for genuinely distinctive Bubba Hub functionality.
7. **Safe and controlled:** never automatically change live listing information without explicit approval.
8. **Mobile friendly:** families and providers should be able to use the platform on their phones.

## 7. Suggested delivery stages

### Foundation
- Directory and content structure.
- Categories, locations and regional discovery.
- Providers, venues, activities and services.
- Search and listing management.
- Pregnancy, baby, toddler and support content.

### Family experience
- Family accounts.
- Favourites and saved activities.
- Family Planner.
- Sessions, recurring schedules and events.
- Links from planner entries to current source information.

### Provider growth
- More capable Leader Hub tools.
- Enquiries and bookings.
- Payments where appropriate.
- Provider subscriptions or enhanced features, if commercially justified.

These are planning stages, not a commitment to build every feature immediately.

## 8. What Bubba Hub should avoid

Bubba Hub should not become:
- A complicated social network.
- A generic business directory without a family experience.
- A booking marketplace that creates excessive administration.
- A collection of unrelated features or duplicated records.
- A bespoke software platform that requires hundreds of custom functions to maintain.
- A product dependent on AI features for its core value.

The technology should support the family experience, not become the product itself.

## 9. Feature decision test

A proposed feature should do at least one of the following:
- Save families time.
- Make family life easier.
- Help families connect with local providers or support.
- Save leaders administration time.
- Help providers reach more families.

If it does none of these, question whether it belongs in the product.

## 10. Technology and migration guardrails

This blueprint defines the product, not a decision to replace the current platform.

Before making technology or migration decisions:
- Audit what already exists and works.
- Identify which existing features can be provided by reliable plugins.
- Identify the small amount of custom integration that is genuinely needed.
- Identify features that can be simplified or retired.
- Preserve existing listings, venues, provider accounts, relationships, planner data and other valuable work wherever possible.
- Do not rebuild from scratch or make live-site changes without explicit approval.
- Prefer a separate proof-of-concept environment for evaluating alternatives.

The guiding approach is: **use established tools for standard directory functionality; keep Bubba Hub's distinctive family experience and planner.**

## 11. Agreed product statement

Bubba Hub is a family discovery, planning and connection platform for pregnancy, babies and young children across Devon and Cornwall.

It brings together local activities, classes, events, venues, services and family support, helping families discover opportunities and organise their lives.

It gives providers and community organisations a simple way to manage their information, promote what they offer and connect with local families.

**One connected platform. Two audiences. One shared purpose: making family life easier.**

---

*This document is the agreed starting point for the next stage: auditing the existing Bubba Hub platform and evaluating the plugins required to deliver this product. It should be updated as product decisions are confirmed.*

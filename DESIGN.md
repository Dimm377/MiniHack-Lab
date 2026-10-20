---
version: alpha
name: MiniHack Web Lab
description: A compact HTTP workbench for local security exercises.
colors:
  background: '#111315'
  surface: '#171a1d'
  surface-raised: '#1c2024'
  text: '#d8dde3'
  muted: '#a0a8b1'
  primary: '#8eafd0'
  border: '#30363d'
  danger: '#df9a9a'
  success: '#a0c1a5'
  danger-surface: 'rgba(223, 154, 154, 0.08)'
  danger-border: 'rgba(223, 154, 154, 0.35)'
  success-surface: 'rgba(160, 193, 165, 0.08)'
  success-border: 'rgba(160, 193, 165, 0.35)'
typography:
  sans:
    fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
  mono:
    fontFamily: 'ui-monospace, SFMono-Regular, Consolas, monospace'
rounded:
  DEFAULT: '3px'
spacing:
  page-max: '1080px'
  section-gap: '2rem'
---

# MiniHack Web Lab design

## Overview

A developer's HTTP inspector and exercise notebook. The audience is people
learning requests, sessions and authorization on their own computer. The UI is
English, uses UTC for stored timestamps, and has no market-specific workflows.
The brief, README and SECURITY.md establish the product boundaries.

Use the existing neutral dark palette. Let request lines, actual response
metadata and clear exercise instructions carry the identity. No hero, dashboard
sidebar, fake metrics, gradients, icons for decoration or animated terminals.

## Colors

`assets/css/style.css` is the canonical token owner; this file mirrors the six
main palette values above. `primary` maps to `--accent`, all others map to their
same-named variables. Links, active navigation and focus use the accent. Muted
text must remain readable; semantic success and danger colors accompany text.

## Typography

System sans for prose, controls and titles. System monospace only for endpoints,
methods, IDs, flags, timestamps and response bodies. Headings use restrained size
and weight; technical labels should not become marketing eyebrows.

## Layout

The shared header has a brand/account row and a navigation row with a visible
current page. Content is at most 1080px wide. Desktop uses two columns only when
there are complementary tasks: catalog/API, exercise/submission, notes/composer.
Below 760px these stack in reading order. Forms shrink without input overflow.
Search and profile use narrow content. Long technical values wrap; JSON has its
own horizontal scroll area. The document owns vertical scrolling.

## Elevation & Depth

Flat surfaces, subtle rules and spacing establish hierarchy. No shadows or blur.
The response body is a slightly darker surface; form regions use the shared
surface token. Avoid nested panels.

## Shapes

Controls have a 3px radius. Catalog entries are divided rows. No pills.

## Components

`includes/header.php` and `footer.php` own chrome and flash feedback; the CSS owns
shared controls, alerts, focus and scrollbars; `assets/js/app.js` progressively
enhances password visibility, form submission and API inspection.

Buttons name actions. Use an outline danger action only at the final note
deletion confirmation. Native disclosure provides a confirmation step without
JavaScript. Forms use server validation, preserve non-secret values, associate
errors with fields and focus the error summary. Native controls remain usable
without JavaScript; API inspection alone needs JavaScript.

Search commits on Enter/submit and stores its query in the URL. It lists at most
20 public users and says so. Notes list all entries in this small local lab.
Creation and deletion return to notes with shared flash feedback. Challenge
submission returns to the same exercise. Released slugs remain immutable.

Flags are deliberately visible training values, not credentials; flag inputs
remain plain text for inspection. Passwords stay masked and can be revealed.
Do not persist passwords, flags or private drafts in browser storage.

All interactive elements have visible keyboard focus, hover and disabled states.
The API keeps response metadata separate from JSON and announces request status.
No decorative motion; reduced-motion and forced-colors remain usable.

## Do's and Don'ts

- Do distinguish public account metadata from private notes and user progress.
- Do show real request/response values and concise recovery messages.
- Don't force every page into the same heading/panel/button layout.
- Don't add a component library, font download, token generator or build step.

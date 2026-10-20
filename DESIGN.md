---
version: alpha
name: MiniHack Web Lab
description: A compact HTTP workbench for local security exercises.
colors:
  background: '#0d0f12'
  surface: '#14181c'
  surface-raised: '#1c2126'
  surface-elevated: '#242a32'
  text: '#dce3eb'
  text-bright: '#f4f7fa'
  muted: '#8d98a6'
  primary: '#8eafd0'
  border: '#21272f'
  border-hover: '#353e4b'
  danger: '#df9a9a'
  danger-surface: '#261616'
  danger-hover: '#351c1c'
  success: '#a0c1a5'
  success-surface: '#142417'
typography:
  sans:
    fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
  mono:
    fontFamily: 'ui-monospace, SFMono-Regular, Consolas, monospace'
rounded:
  DEFAULT: '6px'
  sm: '4px'
  card: '8px'
  pill: '20px'
spacing:
  page-max: '1080px'
  section-gap: '3rem'
---

# MiniHack Web Lab design

## Overview

A developer's HTTP inspector and exercise notebook. The audience is people
learning requests, sessions and authorization on their own computer. The UI is
English, uses UTC for stored timestamps, and has no market-specific workflows.
The brief, README and SECURITY.md establish the product boundaries.

The design embodies a high-craft engineering console: dark tonal surfaces,
restrained accents, clean typographic hierarchy, tactile feedback, and deliberate
absence of excessive 1px wireframe strokes.

## Colors

`assets/css/style.css` is the canonical token owner; this file mirrors the main
palette values above. `primary` maps to `--accent`. Tone steps between `--background`,
`--surface`, and `--surface-raised` provide depth and separation without needing
heavy border strokes.

## Typography

System sans for prose, controls, and titles. System monospace only for endpoints,
methods, IDs, flags, timestamps, and response bodies. Numerical and temporal data
use `font-variant-numeric: tabular-nums` for alignment without monospace costume.

## Layout

Floating header with frosted backdrop blur and touch targets conforming to ≥44px.
Content is centered with a max-width of 1080px. Desktop employs an asymmetric
two-column layout for workbench tasks, collapsing gracefully on viewports below 760px.
Cards and interactive items breathe with generous macro-whitespace.

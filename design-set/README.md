# SCOS Plugin UI — Cursor Handoff

This folder contains everything needed to build the **Site Essentials (SCOS)** plugin UI consistently. Hand the entire `cursor-handoff/` directory to Cursor as your reference.

## What to export to Cursor

Drop these files into your plugin repo and tell Cursor:

> "Use `cursor-handoff/SPEC.md` as the source of truth for all admin UI. Enqueue `tokens.css` and `scos-ui.css` on every SCOS admin page. Match the markup patterns in `snippets.html` exactly."

```
your-plugin/
├── assets/
│   ├── tokens.css       ← copy from cursor-handoff/
│   └── scos-ui.css      ← copy from cursor-handoff/
├── includes/
│   └── admin/           ← Cursor builds PHP here, following SPEC.md
└── cursor-handoff/      ← keep this in repo as living reference
    ├── SPEC.md
    ├── tokens.css
    ├── scos-ui.css
    └── snippets.html
```

## The four files

| File | Purpose |
|---|---|
| `tokens.css` | All design tokens as CSS custom properties. Change `--scos-accent` once → entire plugin reskinned. |
| `scos-ui.css` | All component styles, prefixed `.scos-*`. No JS required. |
| `SPEC.md` | The contract. Menu structure, page templates, class names, do's/don'ts. |
| `snippets.html` | Copy-paste markup for every page type and component. |

## Working with Cursor

**Always:**
- Wrap every admin page body in `<div class="wrap scos">` (the WP `.wrap` keeps native margins; `.scos` scopes our styles).
- Reference tokens, never raw colors. `var(--scos-accent)` not `#4f46e5`.
- Match a snippet from `snippets.html` rather than inventing markup.

**Never:**
- Add inline styles for color, spacing, or typography. Use a class.
- Use third-party CSS frameworks (Bootstrap, Tailwind) — they fight WP admin and our tokens.
- Skip the `.scos` wrapper — without it, our resets don't apply.

## When you ask Cursor for new UI

Paste this preamble:

> Build [thing] for the SCOS plugin. Use the patterns in `cursor-handoff/snippets.html` and the rules in `cursor-handoff/SPEC.md`. Reuse existing classes — don't invent new ones unless `SPEC.md` says to. If you need a new pattern, add it to `snippets.html` first.

This keeps the system from drifting.

# Frontend Design Guidelines

Anthropic's UI/UX principles, applied to this project's stack: Vue 3 (Inertia) + Tailwind CSS v4 with the shadcn-style CSS variable theme in `resources/css/app.css`.

## Core principles

- **Clarity over decoration.** Every element must earn its place. Prefer removing an element over styling it well.
- **Hierarchy through restraint.** Establish visual hierarchy with size, weight, and spacing before reaching for color or borders. One primary action per view — everything else is `secondary`/`ghost`/`outline` variants.
- **Consistency over novelty.** Reuse existing components and utility patterns already in the codebase before inventing new ones. Check `resources/js/components/` for an existing primitive before writing new markup.
- **Accessible by default.** Sufficient color contrast, visible focus states, semantic HTML, and keyboard operability are not optional polish — build them in from the start.
- **Motion with purpose.** Use transitions to communicate state changes (loading, expanding, error), not for decoration. Keep durations short (150–250ms) and respect `prefers-reduced-motion`.

## Vue 3 component conventions

- Single root element per component (Inertia + Vue requirement already enforced in this project).
- Use `<script setup lang="ts">` with typed `defineProps`/`defineEmits`.
- Keep components focused: if a template needs more than ~150 lines or mixes multiple concerns, extract a child component.
- Co-locate variant logic with `class-variance-authority` (or the pattern already used in `components/ui/`) rather than long ternary chains in templates.
- Use `<Transition>`/`<TransitionGroup>` for enter/leave states instead of manual class toggling.

## Tailwind v4 usage

- Use the theme's semantic tokens (`bg-background`, `text-foreground`, `bg-primary`, `text-muted-foreground`, `border-border`, etc. from `@theme inline` in `resources/css/app.css`) instead of raw palette classes (`bg-gray-900`, `text-blue-600`). This is what makes dark mode and future re-theming free.
- Every new visual state must work in both light and dark — verify via the `dark:` variant or (preferably) by relying on the semantic tokens, which already flip automatically.
- Use `gap-*` for spacing between siblings, not margins.
- Compose spacing/sizing on a 4px scale (`p-2`, `p-4`, `p-6`...); avoid arbitrary values (`p-[13px]`) unless matching a hard external constraint.
- Group classes logically in a consistent order: layout → spacing → sizing → typography → color → state (`hover:`/`focus:`/`disabled:`) → dark mode.

## Layout & responsiveness

- Design mobile-first; add `md:`/`lg:` breakpoints to expand, not to fix a broken small-screen layout.
- Prefer `flex`/`grid` with `gap` over absolute positioning or manual margins.
- Never let content overflow horizontally — wrap tables/code blocks in `overflow-x-auto` containers.
- Use `max-w-*` + `mx-auto` for content containers rather than fixed pixel widths.

## States every interactive component needs

- **Default, hover, focus-visible, active, disabled** — all styled, not just default/hover.
- **Loading** — skeletons (pulsing) for deferred/async content, spinners for in-flight actions; never a blank frame.
- **Empty** — an explicit empty state with guidance, not a bare empty list.
- **Error** — inline, specific, and recoverable (what happened + what to do), not a generic toast only.

## Accessibility checklist

- Every interactive element is reachable and operable by keyboard (`Tab`, `Enter`/`Space`, `Esc` for dismissal).
- Visible focus ring (`focus-visible:ring-*`) — never `outline-none` without a replacement.
- Icon-only buttons get an `aria-label` (pull the text from `useTranslations()`, per this project's i18n rules).
- Form inputs have associated `<label>` elements (or `aria-labelledby`), and validation errors are linked via `aria-describedby`.
- Color is never the only signal for state (pair with icon/text for success/error/warning).

## What to avoid

- Inline styles or one-off `style="..."` when a utility class exists.
- New color values outside the theme's CSS variables.
- Custom animation/easing libraries when a CSS `transition`/`<Transition>` suffices.
- Deep prop-drilling for shared UI state — use provide/inject or an existing store pattern already in the codebase.
- Hardcoded user-facing strings — all labels, placeholders, and aria-labels go through `trans()` per this project's i18n convention (see root `CLAUDE.md`).

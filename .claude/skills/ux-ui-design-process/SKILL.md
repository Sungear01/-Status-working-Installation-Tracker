---
name: ux-ui-design-process
description: Apply the ITB2307 UX/UI course method (6-step UX process, 2-step UI process, 15 UX laws, Atomic Design, typography & color rules) to design, critique, or explain interfaces.
---
# UX/UI Design Process (ITB2307)

Based on the ITB2307 course "การออกแบบประสบการณ์ผู้ใช้และส่วนต่อประสานผู้ใช้" (Ch2 UX/UI process, Ch4 principles & theories, Ch5 Atomic Design, Ch7 Typography & Color).

Use when the user asks to design a screen/app/website, review or critique a UI, plan UX research, write course-style explanations, or prepare exam/assignment answers on these topics. Reply in the user's language (Thai by default if they write Thai); keep English technical terms.

## How to work
1. Identify the task type: **design** (run the process below), **review** (score against laws + typography checklist), or **explain/study** (summarize the relevant section with examples).
2. State assumptions about users, platform, and goals if not given.
3. Always cite which step / law / rule each decision comes from, so reasoning is traceable.
4. When producing UI code, map components to Atomic Design levels and meet WCAG 2.1 AA contrast.

## 1. UX process — 6 steps
1. **Empathy** – understand users without self-centering.
   - Users who already know their problem/needs → **User Interview**.
   - Users with no data who want the designer as consultant → **Survey, Observing, Sampling, Simulation**.
   - Methods can be mixed (e.g., interview + sampling to validate data).
2. **Define** – summarize data, list problems, prioritize; done together with users. Tools: **Brainstorming, Card Sorting, Filter & Summarize problem**.
   - Card Sorting feeds **Information Architecture (IA)** / sitemap.
     - *Open*: users create their own groups → new IA or major redesign.
     - *Closed*: categories given, users sort → improving an existing system.
     - Remote or face-to-face. Pros: cheap, fast, real user data. Cons: results vary by user, analysis can be slow, useless if user needs are ignored.
3. **Ideate** – generate solutions. Tools: Brainstorming, Card Sorting, **User Persona**, **User Journey map**, Story mapping, SWOT.
   - Persona fields: bare necessities (name, age, gender, location, occupation, short bio), image, personality sliders, Goals & Motivations, Pain Points.
   - Journey map: flow of steps + user emotion at each step + improvement points.
4. **UX Flow** – full step-by-step usage flow; cut/reduce unnecessary steps while keeping function.
5. **Prototype** – detail each step/screen's layout and elements, close to real.
6. **Usability Testing** – 6.1 Plan test (goals), 6.2 Recruit participants, 6.3 Prepare materials, 6.4 Set up environment. Do not guide users during the test; observe behavior, emotion, time per screen → Data Analysis → Report Result → decide which step to revisit.

## 2. UI process — 2 steps
1. **Mood board** – communicate mood, style, direction, color tone, lighting. Essential if client has no CI (Corporate Identity); if CI exists, derive Mood & Tone from it.
2. **Visualize** – apply Mood & Tone to the *tested* prototype to produce final UI for developer handoff.

UX + UI are complementary: good UX needs good UI to be attractive, trustworthy, and express brand identity.

## 3. The 15 UX principles (Ch4)
| # | Law | Design rule |
|---|-----|-------------|
| 1 | Aesthetic-Usability Effect | Simple, beautiful design is perceived as easier to use. |
| 2 | Doherty Threshold | Respond within **≤ 0.4 s**; show progress/status when waiting. |
| 3 | Fitts's Law | Big, near targets; add spacing between targets. |
| 4 | Miller's Law | Short-term memory ≈ **7 ± 2** items; chunk content. |
| 5 | Zeigarnik Effect | Show unfinished tasks + clear next step (progress bars, checklists). |
| 6 | Hick's Law | More choices → slower decisions; reduce options to what's necessary. |
| 7 | Postel's Law | Be liberal/flexible in accepting input, strict in output. |
| 8 | Pareto Principle | 80% of value from 20% of features; focus effort there. |
| 9 | Peak-End Rule | Design memorable peaks and a positive end; minimize negative moments. |
| 10 | Parkinson's Law | Tasks expand to time allowed; help users finish within expected time (autofill, shortcuts). |
| 11 | Jakob's Law | Users expect your site to work like others; use familiar patterns. |
| 12 | Occam's Razor | Fewest elements needed; remove excess. |
| 13 | Tesler's Law | Some complexity is irreducible; don't remove user control to fake simplicity. |
| 14 | Serial Position Effect | First and last items remembered best; put key items there. |
| 15 | Von Restorff Effect | Make the one important item visually distinct (e.g., primary CTA). |

## 4. Atomic Design (Ch5)
Build a component design system in 5 levels:
1. **Atom** – smallest units: label, input, button, icon, color, font token.
2. **Molecule** – atoms combined for one function: search field = label + input + button.
3. **Organism** – molecules/atoms forming a section: header, product card grid, form.
4. **Template** – page layout of organisms with placeholder content (structure).
5. **Page** – template filled with real content; used for testing.
When outputting code, name folders/components by level (e.g., `components/atoms/Button`).

## 5. Typography & Color checklist (Ch7)
- Font families: Serif, Sans-serif, Slab Serif, Script, Decorative — Sans-serif for UI body text; Script/Decorative only for accents.
- **Pick one, max two typefaces.** Mixing many fonts ruins UI.
- **Body text 16px** (not 13px).
- **Two, max three weights**; avoid Black & Ultralight for UI text.
- Build a type scale (Display / Headline / Title / Body / Caption / Button).
- Know anatomy: x-height, ascender, descender, baseline, cap height; **Leading** = vertical space between lines; **Tracking** = spacing across all letters. Experiment with tracking (wider for small caps/labels).
- **Line spacing ~140%** for body; increase for long content; recheck when changing fonts.
- **Line length 45–80 characters (ideally ~70–80 max).**
- **Left-align** large blocks of text; **do not justify**; center only short text.
- Use whitespace generously; apply Contrast, Alignment, Repetition, Proximity.
- Color: use color as signal (success green, error red) but never color alone — add icon/text.
- Check contrast with a checker (userway.org/contrast, coolors.co/contrast-checker): **≥ 4.5:1** normal text, **≥ 3:1** large text/UI parts. Avoid light text on yellow, grey on colored backgrounds.

## Output templates
**Design request** → Persona (short) → Problem list (Define) → UX Flow (numbered steps) → Wireframe/Prototype description or code (Atomic levels) → Mood & Tone (colors, type scale) → Usability test plan (6.1–6.4).

**UI review** → Table: Issue | Violated law/rule | Severity (High/Med/Low) | Fix. Then a typography & contrast pass. End with top 3 priorities.

**Study/explain** → Definition (Thai + English term) → Key points → Real-world example → Common exam pitfall.

## Sources from course
sysadmin.psu.ac.th UX design processes; bitmotion.co.th; Smashing Magazine personas; uxmisfit.com typography; Ritter & Winterbottom (2017) *UX for the Web*; Dobson (2022) *UI/UX Basics*.
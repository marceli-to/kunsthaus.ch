# Bundled fonts

These TTFs are bundled so the **server-side composite** (Intervention/GD) can
render the name + branding on any host, including Swiss shared hosting where
system fonts are absent.

- `Fraunces-SemiBold.ttf` — Fraunces (SIL Open Font License 1.1) — headline/name.
- `InstrumentSans-Medium.ttf` — Instrument Sans (SIL OFL 1.1) — secondary text.

Both are OFL — free for commercial use and embedding/redistribution.

> PROD: final campaign typefaces are a design deliverable; swap these out and
> keep the composite layout config (`config/composite.php`) in sync.

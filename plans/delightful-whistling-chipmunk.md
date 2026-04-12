# Plan : Enterprise Dashboard Redesign

## Brainstorm — What's wrong today

| Issue | Impact |
|---|---|
| `dashboard.css` still has old sidebar CSS (`margin-left: -15rem`, `min-width: 100vw`) | **Breaks the new sidebar layout** |
| `style.css` contains `body { color: red; }` | Turns all body text red globally |
| Stat cards use Bootstrap 4 classes (`border-left-primary`, `text-gray-300`) that don't exist in Bootstrap 5 | Cards look broken/dated |
| Chart colors (`#547792`, `#94B4C1`) don't match the brand palette | Visual inconsistency |
| `dashboard.php` passes `{{flash_script}}` instead of `{{flash_json}}` | Flash messages broken on dashboard |
| `{{flash_json}}` and `{{validation_errors_json}}` not replaced in layout → `JSON.parse('{{flash_json}}')` throws SyntaxError | JS error on every dashboard load |
| No page header quick actions | Poor UX — forces nav back to sidebar |
| Stat cards are generic, low contrast | Low visual clarity |

---

## What enterprise dashboards do well (Linear, Vercel, GitHub)
- **Clean KPI cards**: large number, icon with colored background, subtle label, mini trend
- **Brand-consistent charts**: colors match the design system
- **Header with context + quick actions**: date, role, action buttons
- **No visual clutter**: generous whitespace, subtle borders

---

## Files to modify

| File | Change |
|---|---|
| `assets/css/dashboard.css` | Remove old sidebar CSS; add stat card + dash header CSS |
| `assets/css/style.css` | Remove `body { color: red; }` |
| `dashboard.php` | Fix flash (pass `flash_json`), add `recent_month` stat |
| `templates/admin/pages/dashboard.html` | Full redesign |

---

## Design decisions

### Stat cards (4 cards)
```
┌──────────────────────────────┐
│ [🎓]  1 234                  │
│       Total inscrits         │
│       +8 cette semaine       │
└──────────────────────────────┘
```
- Icon in rounded square with colored soft background
- Large bold number
- Uppercase micro-label
- Green sub-line with trend

Cards:
1. **Total inscrits** — icon `fa-users`, green → `{{stats_total}}` / `+{{recent_week}} cette semaine`
2. **Nouveaux (30j)** — icon `fa-calendar-plus`, yellow → `{{recent_month}}`
3. **Répartition M/F** — icon `fa-venus-mars`, dark → `{{stats_hommes}}♂ / {{stats_femmes}}♀`
4. **Par statut** — icon `fa-layer-group`, red → `Ét. {{stats_etudiants}} · Él. {{stats_eleves}} · St. {{stats_stagiaires}}`

### Page header
```
Tableau de bord                [Étudiants ↗]  [Exporter ↗]
Bienvenue, Prénom · administrateur
```

### Charts
- **Gender** (donut): green `#009460` + yellow `#FCD116`
- **Status** (donut): green + yellow + red
- **Schools** (bar): green `#009460`, hover `#1B4D3E`

---

## `dashboard.php` changes

1. Fix flash: replace `$flash_script` with proper `$flash_json`
2. Add `recent_month` query
3. Pass `{{flash_json}}` and `{{validation_errors_json}}` to layout strtr

```php
// Add:
$recentMonth = (function() use ($conn) {
    $q = $conn->prepare("SELECT COUNT(*) FROM personnes WHERE date_enregistrement >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $q->execute();
    return $q->fetchColumn();
})();

// Fix layout strtr:
'{{flash_json}}'              => $flash_json,
'{{validation_errors_json}}'  => '',
// Remove: '{{flash_script}}'
```

---

## `dashboard.css` — keep only

```css
.chart-container { position: relative; height: 40vh; width: 100%; }

/* Stat cards */
.stat-card { ... }
.stat-icon { ... }
/* etc. */

/* Dash header */
.dash-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; }
```

---

## Verification (review step)
- [ ] No `color: red` on body text
- [ ] Sidebar not broken (old dashboard.css conflict removed)
- [ ] 4 stat cards display cleanly with correct numbers
- [ ] Flash messages show on dashboard (SweetAlert)
- [ ] Charts use brand colors
- [ ] Header quick-action buttons work (link to students.php, export.php)
- [ ] Mobile: cards stack to 2-col then 1-col
- [ ] No JS errors in console (`flash_json` replaced correctly)

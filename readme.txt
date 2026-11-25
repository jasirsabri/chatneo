=== ChatNeo ===
Contributors: Consyst Group
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.10.4
License: GPLv2 or later

A floating, conversational WhatsApp chat widget built on a fully generic
Route engine. Each destination department (Sales, Careers, Support, or
anything you define in the Route Builder) has its own questions, its own
ending (a WhatsApp handoff or a direct contact message), and its own
optional ERPNext CRM mapping — no two routes need to look alike, and
adding a new one never requires touching code. Every submission is
stored locally as a fallback, viewable and exportable to CSV from
ChatNeo > Submissions.

== Installation ==

1. In wp-admin, go to Plugins > Add New > Upload Plugin.
2. Choose the chatneo.zip file and click Install Now.
3. Activate the plugin.
4. Go to ChatNeo > Settings and enter your WhatsApp number
   (with country code, e.g. +919876543210).
5. Go to ChatNeo > Route Builder to review the routes (Sales, Support,
   etc. by default) — edit their questions, endings, and CRM mapping,
   or add new ones entirely.
6. Save. The chat bubble will now appear on your site.

== Important note on WhatsApp messages ==

WhatsApp's click-to-chat links pre-fill the message text, but the
visitor still has to tap Send themselves once WhatsApp opens. This is
a platform-level restriction (not something this plugin can bypass) —
businesses are not permitted to send the first message to someone who
hasn't messaged them first. Having the visitor's own device send the
first message is what starts a compliant conversation that Whatomate
can then pick up.

== Changelog ==

= 1.10.4 =
* Route Builder: each route's CRM section now has a "Lead owner" field.
  Enter the email of the ERPNext user new leads should be assigned to
  (it must match an existing ERPNext user). Leave it blank to keep the
  old behaviour, where ERPNext assigns the lead to the API user.

= 1.10.3 =
* Route Builder: each route's CRM section now has a "Fixed values"
  box. Use it for fields your CRM requires but visitors are never asked
  — for example, if your CRM rejects new Leads with "Value missing for
  Lead: Source", enter "source = Website" (one "fieldname = value" per
  line). These values are sent with every submission for that route.
  Existing routes keep working unchanged.

= 1.10.2 =
* Fixed the chat widget on phones: instead of the small popup used on
  desktop, it now opens as a fullscreen sheet that resizes itself to
  stay above the on-screen keyboard, so the message you're typing (and
  the Send button) never end up hidden underneath it.
* Fixed the phone browser auto-zooming the page when you tapped into
  the chat's text field, which could otherwise throw the layout off.

= 1.10.1 =
* With only one route configured, the widget now skips the "which
  department" question entirely and goes straight into that route's
  own questions after Name — no point choosing between departments
  when there's only one.

= 1.10.0 =
* Submissions page: added checkboxes and a "Delete Selected" bulk
  action, alongside the existing per-row Delete link.
* Added responsive layout fixes across all ChatNeo admin pages
  (Settings, Route Builder, Submissions) — the Route Builder's route
  and question rows now stack vertically on narrow screens instead of
  overflowing, and the Submissions table collapses into stacked cards
  on mobile instead of squeezing columns unreadably.
* No database changes in this release.

= 1.9.1 =
* Route Builder: added up/down move buttons to reorder questions within
  a route (routes themselves already had this) — the previously
  mentioned gap.
* No database changes in this release.

= 1.9.0 =
* Major architecture rewrite: replaced the hardcoded Sales/Transfer
  split with a generic Route engine. Every destination department —
  Sales, Careers, Support, or anything you define — is now data (its
  own questions, its own ending, its own optional CRM mapping)
  executed by one engine, instead of special-cased code per route.
* New Route Builder admin page (ChatNeo > Route Builder): add, remove,
  and reorder routes; define each route's questions (field key, type,
  prompt, required); choose WhatsApp handoff or a direct contact
  message as the ending; and optionally enable a CRM push with its own
  DocType and field mapping — all without touching code.
* ERPNext integration is now generic per-route: the connection itself
  (base URL, API key/secret) is one shared setting in ChatNeo >
  Settings, but which DocType and which fields each route pushes to
  is fully configurable per route — e.g. a Sales route can create a
  "Lead" while a Careers route creates a "Job Applicant", using the
  same connection.
* New unified Submissions admin page (ChatNeo > Submissions) replaces
  the separate Leads and Transfers pages, with a route filter and CSV
  export whose columns adapt to whatever each route actually asks.
* Existing installs are upgraded automatically and safely: a one-time
  migration reads your current Settings (Sales message template, CRM
  mapping, department list) and generates equivalent routes, so
  upgrading changes nothing about how the widget behaves until you
  actively edit something in the Route Builder. Existing Leads and
  Transfers history is copied into the new Submissions table with
  original timestamps preserved; the old tables are kept, untouched,
  as a safety net.

= 1.8.0 =
* Redesigned the bubble on desktop from a plain circular icon into a
  pill-shaped bar showing an icon plus a label (the existing "Bubble
  label text" setting, previously only used as a hover tooltip and the
  panel's header title).
* Added an optional "We are online" sticker — a persistent badge above
  the bubble (not a hover tooltip), with its own toggle and editable
  text in Settings.
* Responsive by design: on mobile (≤420px) the bubble collapses back
  to a plain icon-only circle to avoid crowding the screen edge, and
  the sticker becomes a small dot badge on the bubble itself instead
  of the full text card. Both the bar and the sticker also collapse to
  their compact forms whenever the chat panel is open, on any screen
  size.
* Removed the old hover-only tooltip — the label is now always visible
  on desktop instead of requiring a hover to reveal it.
* No database changes in this release.

= 1.7.0 =
* Departments can now be configured to show their contact details
  directly in the chat instead of routing to WhatsApp — e.g. "Support
  Team is not available on Chat. You can reach them on phone X or
  email Y." Configured per department in Settings using the format
  "Name | direct | phone | email"; plain department names keep
  routing to WhatsApp exactly as before.
* New "Direct contact message" template (Settings) with {department},
  {phone}, {email} placeholders.
* Transfers are still logged (name, department, phone) for these
  departments too — only the final step (WhatsApp vs. direct message)
  differs.
* No database changes in this release.

= 1.6.2 =
* Sales WhatsApp message default now opens with "Hi, I'm a customer
  and I'd like to chat." instead of "Hi, I'd like to chat." — this is
  a default-value change only; it won't retroactively update the
  Message text already saved in Settings on an existing install.

= 1.6.1 =
* Reworded the department question from "What are you looking for?" to
  "Which department would you like to reach out to?" — clearer now
  that it's a department picker rather than an open question.
* Reworded the Sales completion message to reflect that the lead has
  already been shared with the sales team, framing WhatsApp as an
  optional way to chat further rather than "the" next step.

= 1.6.0 =
* Renamed the plugin to ChatNeo (display name only — internal database
  tables, option keys, and identifiers are unchanged, so this upgrade
  is safe for existing installs with live leads/transfers data).
* Sales path: added a new "briefly describe what you need" question
  after the department is picked. The department selection itself is
  now stored as its own "destination department" field, separate from
  this new description — previously the department name was
  overloading the "intent" field. Settings, the Leads admin page, CSV
  export, message template placeholders ({department} is new; {intent}
  now means the description), and the CRM field mapping (new
  "Destination department" row) were all updated to match.
* Fixed a bug where the chat panel would close immediately after
  picking a department. Root cause: removing the button group from the
  DOM synchronously (inside its own click handler) detached it before
  the same click event finished bubbling to the "click outside closes
  the panel" listener, which then wrongly read that as an outside
  click. The removal is now deferred to the next tick.
* Added a "Foreground Colour" setting alongside the existing colour
  (now labeled "Background Colour"). Foreground controls the bubble
  icon, header text, your visitor's message text, and the send button
  icon — previously hardcoded to white regardless of the chosen brand
  colour.
* Existing installs are upgraded automatically (new "department"
  column is added to the Leads table without affecting previously
  collected leads).

= 1.5.1 =
* Non-Sales department picks now also ask for a phone number before the
  WhatsApp handoff — no email, no CRM push — so there's a way to follow
  up later even if the visitor doesn't continue on WhatsApp.
* The Transfers table and CSV export now include a Phone column.
* "Transfer message" template gained a {phone} placeholder; default
  template updated to include it.
* Existing installs are upgraded automatically (new "phone" column is
  added to the Transfers table without affecting previously logged
  transfers).

= 1.5.0 =
* Replaced the free-text "what are you looking for" question with a set
  of department buttons — configurable in Settings under "Departments &
  Routing". No more guessing intent from typed text.
* Only the designated Sales department continues to Phone, Email, and
  (if configured) the CRM push, exactly as before. Picking any other
  department skips straight to a WhatsApp handoff with no further
  questions asked — no phone/email collected, no CRM push.
* New lightweight "Transfers" log (its own admin page + CSV export)
  records just the visitor's name, chosen department, and page — kept
  separate from the Leads table since it's a genuinely different kind
  of record.
* New "Transfer message" template (Settings) for the pre-filled
  WhatsApp message used on this path, with {name} and {department}
  placeholders.
* Existing installs are upgraded automatically (new Transfers table is
  created without affecting previously collected leads).

= 1.4.0 =
* Added CRM push: when a lead completes the conversation, its details
  can be pushed to an ERPNext (Frappe) instance via its REST API, using
  token authentication (Authorization: token api_key:api_secret).
* New "CRM Integration (ERPNext)" section in Settings: base URL, API
  Key, API Secret, target DocType, and a field-mapping table so each
  piece of lead data can be matched to your DocType's actual field
  names — nothing is hardcoded to a guessed schema.
* The Leads page now shows a CRM column (Synced / Failed, with the
  error visible on hover for failed pushes) and this is included in
  the CSV export. A failed or skipped CRM push never blocks the
  WhatsApp handoff — the local save always succeeds regardless.
* Existing installs are upgraded automatically (new database columns
  are added without affecting previously collected leads).

= 1.3.5 =
* Fixed the country dropdown extending past the bottom of the screen
  on smaller windows, cutting off the last few entries. The list now
  has a capped height and simply scrolls for anything beyond that,
  regardless of window size.

= 1.3.4 =
* Replaced the default browser scrollbar on the chat window with a
  thin, understated one, and applied the same style to the country
  dropdown's list.
* Fixed scrolling inside the country dropdown handing off to the page
  behind it once you reached the top/bottom of the list — the list now
  contains its own scroll instead of passing it through.

= 1.3.3 =
* Fixed the country dropdown appearing clipped (only its search box
  visible) — it now renders in the page body instead of inside the
  chat panel, escaping the panel's own overflow:hidden.
* The input field and Send button are now disabled while the bot is
  "typing" a message, and only become clickable once that message has
  actually finished appearing — previously the field looked typeable
  the whole time, even before a question had been shown.

= 1.3.2 =
* Fixed the phone field's country-code dropdown not responding to
  clicks. It was being initialized while its container was still
  hidden (before the visitor reached that step), which left the
  dropdown's position/size miscalculated. It now initializes the
  moment the phone step becomes visible instead.

= 1.3.1 =
* Changed "Could I get your name to get started?" to a direct request
  — "Please share your name to get started." — since the question
  phrasing sometimes drew replies like "yes" or "sure" instead of an
  actual name. Updated the retry message to match.

= 1.3.0 =
* Fallback greeting (used when Site Name / Agent Name aren't configured)
  now reads "Hi there! Thanks for stopping by." followed by the name
  question as a separate message, instead of one combined line.
* Progress is now saved to the database after every answered question,
  not just at the end — so if a visitor leaves partway through (e.g.
  after sharing their name and what they're looking for), that partial
  information is still captured rather than lost. The Leads page shows
  a Status column (Complete / Partial) to distinguish these at a glance.
* Added page-visit tracking: the pages a visitor looked at during their
  session are recorded alongside each lead (visible in the Leads table
  and CSV export as "Pages Visited").
* Existing installs are upgraded automatically (new database columns
  are added without affecting previously collected leads).

= 1.2.1 =
* Personalized intro now sends as three separate chat messages instead
  of two lines joined with a dash: agent intro, thank-you, then the
  name question — reads more like a real person typing one thought at
  a time.
* Reworded the WhatsApp handoff message to sound like a smooth
  transition ("Let's carry this over to WhatsApp so we can help you
  right away") instead of "we have everything we need."

= 1.2.0 =
* Added configurable Site/Company Name and Agent Name — the chat widget
  now introduces itself (e.g. "Hey! I'm Sarah from Acme Corp.") before
  asking questions, when both are set.
* Added a new "What are you looking for?" question to the conversation,
  captured as Intent and shown in the Leads table, CSV export, and
  available as a {intent} placeholder in the WhatsApp message template.
* Bot reply timing now varies naturally based on message length instead
  of a fixed delay, to feel more like a real person typing.
* Existing installs are upgraded automatically (new "intent" database
  column is added without affecting previously collected leads).

= 1.1.0 =
* Redesigned the widget as a docked, conversational chat panel anchored
  to the bubble (previously a separate centered form).
* Added a Delete action for individual leads in the admin Leads page.

= 1.0.0 =
* Initial release.

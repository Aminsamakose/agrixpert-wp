# Real Content Migration (Phase 10 prep)

This is the "real content" item the charter flagged as outstanding for Phase 10: the actual copy from the live `agri-xpert.vercel.app` Next.js app, migrated into this WordPress plugin, as opposed to placeholder text. No invented copy is used anywhere in this document or in the code changes it documents — every line below is either copied verbatim from the live app's source (`src/app/**/page.tsx`, `src/lib/site-data.ts`, `src/lib/value-chain-content.ts`, `src/lib/solutions-content.ts`) or explicitly marked as something that still needs a decision from Amin.

**Update (2026-11-23):** every page described below as "paste into a WP Page" is now created automatically on activation by `includes/class-page-seeder.php` — About, What We Do, For Farmers, For Buyers, For Cooperatives, Partnerships, Contact, Marketplace, Portal, the three legal pages, the Value Chains and Solutions index pages, and all 11 child pages (5 crops + 6 solutions, correctly nested under their index page via `post_parent`, not a slash in the slug). It only creates a page if nothing already exists at that slug, so re-activating or updating the plugin never overwrites a page Amin has since edited by hand. The sections below describing this as a manual task are kept for the record of what was migrated and from where; the mechanism is now automatic.

## What got wired up as working code, not just copy-pasted text

Checking what the schema actually has running code against it (the same method that found the Portal gap in Phase 9.5) turned up a second table with zero code anywhere outside `class-schema.php`: **`page_content`**, meant to hold a JSON override per value-chain/solution page. It existed since Phase 1 and was never read or written by anything. This is now fixed:

- **`includes/data/class-page-library.php`** — loads the real value-chain and solution content (bundled as `data/value-chains.json` and `data/solutions.json`, ported directly from the live app's `value-chain-content.ts` / `solutions-content.ts`) and merges in any admin override stored in `page_content`, mirroring the live app's own `override ?? fallback` pattern exactly.
- **`includes/public/class-page-library-shortcodes.php`** — four new shortcodes:
  - `[agrixpert_value_chains]` — grid of all 5 value chains (Maize, Soybeans, Rice, Groundnuts, Vegetables)
  - `[agrixpert_value_chain slug="maize"]` — full detail page for one (overview, sourcing, farmer network, coordination, aggregation, quality, markets, partnerships, closing)
  - `[agrixpert_solutions]` — grid of all 6 solutions
  - `[agrixpert_solution slug="production-coordination"]` — full detail page for one

Value-chain slugs: `maize`, `soybeans`, `rice`, `groundnuts`, `vegetables`.
Solution slugs: `production-coordination`, `aggregation-procurement`, `mechanisation-farm-services`, `storage-supply-chain`, `processing-value-addition`, `market-access-trade`.

**To use these:** create a WP Page at `/value-chains/maize` with the content `[agrixpert_value_chain slug="maize"]`, repeat per crop/solution, and create index pages at `/value-chains` and `/solutions` with the listing shortcodes. No PHP template files or custom routing needed — this follows the "plain WP admin pages" decision from Phase 1.

If Amin later wants to edit one page's content without touching code, the admin tools already exist for this (Content admin already has an Insights CMS; a page_content editor UI does not exist yet and would be a small follow-up — for now an override can be written directly via `AgriXpert_Content::set_page_content( 'value_chain', 'maize', $data )`).

**Images:** every `heroImageKey` resolves against `data/stock-images.json`, which bundles the exact Unsplash URLs the live app hotlinks (same license, same placeholder status — the live app's own comment calls these "placeholders, not AgriXpert's actual farmers, fields or sites"). Replacing them with real photography is unchanged as an open item from before this migration; it isn't new scope this adds.

## Site Content defaults (now real copy, not placeholders)

`includes/class-activation.php`'s `seed_defaults()` previously seeded four placeholder fields with invented copy ("Transforming Agribusiness Across Ghana" did not appear anywhere in the live app). It now seeds real hero copy for every major page, using the exact same dotted key convention the live app already used for its own `site_content` table (`home.hero.title`, `about.hero.title`, etc.), so the keys carry over unchanged if this is ever cross-checked against the live Supabase data again:

| Key | Value |
|---|---|
| `home.hero.title` | Connecting Agricultural Production to Profitable Markets. |
| `home.hero.description` | AgriXpert builds commercially sustainable agricultural supply chains by connecting farmers, agricultural service providers, processors and buyers through coordinated production, aggregation, value addition and market access. |
| `about.hero.title` | Connecting Agricultural Production to Profitable Markets |
| `about.hero.description` | AgriXpert is a market-led agricultural value chain integration company focused on connecting farmers, agricultural businesses, service providers, processors, commodity suppliers and commercial buyers. |
| `what-we-do.hero.title` | Production, Aggregation, Mechanisation and Supply Chain Solutions |
| `what-we-do.hero.description` | AgriXpert connects every stage of the agricultural value chain into one coordinated commercial system — from farm to firm. |
| `for-farmers.hero.title` | Farmer registration, production support and market access |
| `for-buyers.hero.title` | Commodity sourcing, procurement and supply agreements |
| `for-cooperatives.hero.title` | Cooperative registration, group market access and member management |
| `partnerships.hero.title` | Institutional, commercial and investment partnerships |
| `contact.hero.title` | Enquiries and business contacts |
| `contact.email` | agrixpert@samakose.com (decided with Amin 2026-11-23, replacing both the live app's `agrixpert26@gmail.com` and the previous default of the installing WP admin's own email) |
| `contact.phone` | +233 (0) 55-858-9254 (from the live app's footer) |
| `contact.address` | Tamale, Northern Ghana |

`contact.email` seeds to `agrixpert@samakose.com` — confirmed with Amin on 2026-11-23, not the live app's own `agrixpert26@gmail.com` and not the WP installer's email.

## Short reusable lists (for the pages built from them)

These back the Home, For Farmers, For Buyers and For Cooperatives pages. They are static arrays in the live app (not stored in Supabase, so there's no "override" table for these the way there is for hero text or value-chain/solution pages) — the Home page in particular is almost entirely built from these, not from paragraph copy. The practical path for Phase 10: type these directly into the matching WP Page's body (as a list block) rather than building six more shortcodes for arrays this short and this rarely changed.

**The problem we're solving (Home):**
- Fragmented Production — Farmers operate independently, making it difficult to coordinate reliable commercial supply.
- Post-Harvest Losses — Limited storage, handling and processing infrastructure affects product quality and value.
- Market Access Barriers — Farmers struggle to connect with reliable buyers and commercial markets.
- Production Constraints — Limited access to mechanisation, inputs, financing and technical services.

**What sets AgriXpert's model apart (Home):**
- Northern Ghana Depth, Not a Generic Footprint — Our focus is deliberately narrow: the Northern, Savannah, North East, Upper East and Upper West regions. We build deep operating relationships in these corridors rather than spreading thin across the country.
- Access Integration, Not Infrastructure Duplication — Our role is to get farmers and agricultural businesses access to markets, mechanisation, storage, processing, finance and buyers, coordinating this access as one connected relationship rather than leaving farmers to find and negotiate with each specialist provider separately.
- Enterprise Development Track Record — AgriXpert's model is led by enterprise development practice, not production alone: our leadership has directly supported more than 50 agribusinesses on the operational and commercial fundamentals that make a value chain actually work.

**We integrate the agricultural value chain (Home):**
1. Coordinate agricultural production.
2. Aggregate and manage agricultural commodities.
3. Connect producers to commercial buyers.
4. Facilitate processing, logistics and value addition.

**From farm to market — the business model steps (Home):**
1. Farmer mobilisation — Identify farmers, cooperatives and production communities.
2. Production coordination — Coordinate production plans, inputs, mechanisation and technical support.
3. Aggregation and quality control — Collect, grade, weigh and consolidate commodities.
4. Storage and processing — Manage post-harvest handling, warehousing and processing partnerships.
5. Commercial market linkage — Connect commodities to verified buyers and institutional markets.
6. Delivery and performance measurement — Manage fulfilment, transactions, traceability and supply-chain performance.

**For Farmers — benefits:**
- Access to structured markets.
- Production planning and technical support.
- Mechanisation and agricultural services.
- Commodity aggregation.
- Quality and post-harvest support.
- Potential access to finance through financial partners.

**For Buyers — what you can do:**
- Submit commodity sourcing requests.
- Specify commodity, quantity and quality.
- Access aggregated supply opportunities.
- Establish procurement relationships.
- Explore long-term supply arrangements.

**For Cooperatives — benefits:**
- Register your cooperative, VSLA, farmer group or association once, as a single entity.
- Access clustered-demand opportunities sized for group rather than individual volumes.
- Post and manage marketplace listings on behalf of your members.
- Maintain a membership record linked to AgriXpert's farmer registry.
- Strengthen your group's standing with buyers, suppliers and development partners.

**Digital capabilities (Home, labelled "Planned" on the live site — carry that label over, don't present these as live):**
Digital farmer registration; farm and production records; commodity availability tracking; buyer and supplier management; commodity pricing and market intelligence; digital traceability; supply-chain performance dashboards.

**Impact indicators table (Home — every value column reads "Figures pending verification" on the live site; do not invent numbers here either):**

| Indicator | Measurement |
|---|---|
| Farmers engaged | Number of registered and active farmers |
| Production coordinated | Hectares or tonnes |
| Commodities aggregated | Tonnes per season |
| Buyers connected | Active commercial buyers |
| Jobs supported | Direct and indirect employment |
| Women and youth | Participation and economic opportunities |

## Long-form pages — paste directly into a WP Page

These are narrative prose, not list-driven, so the practical route is pasting the text into the WP block editor rather than building a shortcode for a page that will rarely change. Full text for each is in the live app at the path noted; summarized here is what each page covers so nothing is lost in translation, not the full verbatim copy (which runs long — About alone is nine sub-sections). Pull the exact wording from the live app's own source before publishing, since legal text in particular should not be paraphrased:

- **About** (`src/app/about/page.tsx`) — Our Story, Why AgriXpert Exists, Where We Operate (Northern/Savannah/North East/Upper East/Upper West regions; Maize/Soybeans/Rice/Groundnuts/Vegetables), What Makes AgriXpert Different (the six solution pillars), Mission & Vision, six Strategic Objectives, six Core Values, Leadership (Amin Yahaya, CEO — bio and a pull-quote), eight areas of expertise network, closing commitment statement.
- **Privacy Policy** (`src/app/privacy-policy/page.tsx`) — 11 sections, framed against Ghana's Data Protection Act, 2012 (Act 843). Carries an explicit note that it's a Phase 1 draft pending legal review before the platform processes personal data at scale — carry that note over; don't present it as finalized.
- **Cookie Policy** (`src/app/cookie-policy/page.tsx`) — 6 sections. Same Phase-1-draft note, plus a note that no cookie-consent mechanism exists yet.
- **Terms of Service** (`src/app/terms-of-service/page.tsx`) — 13 sections, governed by the laws of Ghana. Same Phase-1-draft note.

The live app's legal pages route "Contact Us" to `agrixpert26@gmail.com`; the seeded WP pages (see below) use `agrixpert@samakose.com` instead, consistent with the `contact.email` default above.

## What this does not cover

- **Insights** (blog/news posts) are live, admin-authored content in the live app's Supabase `insights` table, not static source code — there is nothing fixed to "migrate" here, since this is exactly what the plugin's existing Insights CMS (Phase 7) is for. If Amin wants specific existing published insights carried over, that needs the live Supabase data itself, not the Next.js source, and is a separate, narrower task.
- **Real photography** replacing the Unsplash placeholders — unchanged open item, not new scope from this pass.
- **Marketplace listings, verification records, CRM data** — these are live operational data in the Supabase database, not static site content, and are out of scope for a content migration in the sense used here.

<?php
/**
 * Version of THIS bundled copy of the POSIMYTH Analytics SDK.
 *
 * Read by posimyth-sdk-loader.php to decide which plugin's copy of the shared classes wins when
 * several POSIMYTH plugins are active. Bump it on ANY change to the shared SDK files, or an
 * updated plugin's fixes can silently lose to a sibling's older copy.
 *
 * 2.21.0a — two corrections made inside 2.21.0 without a bump, by request, so this note is the only
 * record that the contents changed: a copy of 2.21.0 taken before 2026-08-12 is NOT the same file as
 * this one. Diff before assuming a re-sync is a no-op.
 *
 * SECOND CORRECTION — the consent notice's render-once flag is now keyed by suite. It was a single
 * install-wide bool while consent is per suite, so with two suites active only the FIRST to run
 * rendered and the other suite was never asked on that request. Hook order is stable, so the same
 * suite won every time and the loser only got its turn once the winner had been answered: a user who
 * ignored the first notice was never asked by the other product at all. Measured before the fix, with
 * both suites due: 1 of 2 notices rendered. After: 2 of 2 for different suites, and still 1 of 2 for
 * two products sharing one suite_key, which is the case the flag exists for — Nexter Extension and
 * Nexter Blocks share `nexter_suite` and one consent option, so a second notice there is a duplicate
 * of the one already on screen. It failed in the safe direction (never asked, so nothing sent), which
 * is why it could sit there unnoticed.
 *
 * FIRST CORRECTION — the default accent is neutral again.
 *
 * The 2.20.0 entry below claims the copies are byte-identical apart from their text domain. That was
 * not true when checked on 2026-08-12: each copy had drifted its `accent` default and its CSS var()
 * fallbacks to its OWN brand — this one to #9d1a4f in seven places in the notice and one in the
 * survey, The Plus Addons' to #6660EF — while the notice's own comment said, correctly, that the
 * default must never be a real product's colour, because the default is precisely what paints a
 * sibling that passes none. All eight are now #1717cc, which the survey's own validation and CSS
 * fallbacks already used, so the two files finally agree with each other and with their comments.
 *
 * Nothing visible changes for a product that passes `accent`, and every POSIMYTH product does: the
 * passed value arrives as an inline custom property and always wins. The default and the fallbacks
 * were unreachable in the two-plugin case testable here, so the bug was latent — aimed at whichever
 * product passes nothing.
 *
 * Still to do, and the claim below stays false until it is: The Plus Addons, Nexter Extension and
 * Nexter Blocks carry their own colour in those same eight places. Apply the identical change there.
 * Until then two different files both call themselves 2.21.0, the loader breaks that tie on
 * registration order alone, and which default a no-accent product gets depends on plugin load order.
 *
 * 2.21.0 — a failed ping is no longer silent. do_request() discarded the hub's response entirely, so
 * a hub that accepted the request and then failed to store it was invisible to every product at once:
 * pings kept going out, rows stopped arriving, and nothing anywhere said so. It was found by probing
 * the endpoint by hand, which is not a monitoring strategy. Under WP_DEBUG, and only for the blocking
 * path where a status actually exists, a non-2xx reply now writes one line to the debug log. Nothing
 * changes for a site with debugging off.
 *
 * 2.20.0 — the reconciliation the previous revisions kept asking for. All four plugins now ship the
 * SAME three shared files, byte for byte apart from their text domain, and the SAME version number.
 * That restores the loader's own stated assumption ("the copies are byte-identical apart from their
 * text domain, so a tie has no wrong answer") and, with it, the point of this file: it now records
 * which BUILD a copy is, and no longer decides whose design or whose feature set every sibling gets.
 *
 * What was reconciled, and what each divergence had been costing:
 *
 *  - Consent notice: the 2.19.0 treatment is now everyone's. The accent arrives as an inline
 *    `--posi-accent` custom property on each instance's own markup and the stylesheet keys only on
 *    the stable `posi-*` classes, so one stylesheet serves N brand colours. Before this, three copies
 *    baked a literal colour and a product-specific class prefix into a stylesheet that is printed
 *    ONCE for the whole page — which is how The Plus Addons' purple painted Sticky Header Effects'
 *    notice, and Sticky Header Effects' raspberry painted The Plus Addons'. Never reintroduce a
 *    prefix-keyed selector or a literal colour here: the stylesheet's instance and the markup's
 *    instance are latched separately, so they are not guaranteed to be the same product.
 *
 *  - Deactivation survey: the 2.16.0 design (accent top border, backdrop blur, 575px dialog) plus
 *    callable `reasons`. The callable support existed only in the Nexter copies while a copy without
 *    it held the highest version, and a copy that only accepts arrays does not error on a closure —
 *    is_array() is simply false, so the config collapsed to the built-in seven. Nexter Extension and
 *    Nexter Blocks both pass a closure, so both silently shipped the generic reason list instead of
 *    their own branded cards. Callables are the supported way to defer __() out of `plugins_loaded`,
 *    so dropping that block also re-breaks WP 6.7+ translation timing.
 *
 * Every product passes its own `accent` (and the legacy `css_prefix`) explicitly, even where the
 * value equals this SDK's neutral default. Branding is never inherited: a product that passes nothing
 * is painted by whichever copy won the loader, and that is precisely the coupling this revision
 * removes. The defaults here stay product-neutral for the same reason.
 *
 * Keeping it this way: change the shared files in ONE place, re-sync all five, bump this number in
 * all five together. A copy that diverges again re-creates the exact failure mode above, and it fails
 * silently — nothing errors, a sibling's design or defaults simply take over.
 *
 * @package POSIMYTH\Analytics\SDK
 */

return '2.21.0';

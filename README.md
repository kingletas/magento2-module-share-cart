# Commerce_ShareCart

Lets a shopper generate a link that recreates their cart for whoever opens it. Useful for group ordering (a team kitting out with the same uniform) and for "here's what I picked, what do you think?".

Installs and runs on its own. It requires no payment extension, no validation library and no in-house stdlib.

---

## How it works

1. The shopper clicks **Share My Cart** on the cart page.
2. `sharecart/cart/generate` snapshots the live quote into a **detached, inactive** quote, mints a 128-bit token, and stores the token's **SHA-256 digest** against the snapshot.
3. The shopper gets `…/sharecart/cart/share/token/{token}`.
4. Opening that link merges the snapshot into the visitor's own cart — their existing items are kept, not replaced.
5. A nightly cron removes links past their expiry.

The plaintext token exists only in the URL. Anyone with read access to the database sees digests, so a database leak does not hand over shoppers' carts.

---

## Installation

```bash
composer require commerce/module-share-cart
bin/magento module:enable Commerce_ShareCart
bin/magento setup:upgrade
```

---

## Configuration

**Stores → Configuration → Sales → Share Cart**

| Setting | Default | Notes |
| --- | --- | --- |
| Enable Cart Sharing | Yes | Turns the control and both routes off when No |
| Link Lifetime (days) | 30 | `0` never expires |
| Delete Expired Links by Cron | Yes | Runs `0 3 * * *` |

A share link reveals a basket to anyone holding it, so leaving an expiry set is the safer default.

---

## Express checkout buttons

This module has **no** knowledge of any payment provider. To show express buttons next to the share control, register adapters in the pool — no PHP needed for the common case:

```xml
<virtualType name="Acme\ShareCart\ExpressButton\PayPal"
             type="Commerce\ShareCart\Model\Checkout\BlockExpressButton">
    <arguments>
        <argument name="code" xsi:type="string">paypal</argument>
        <argument name="blockClass" xsi:type="string">Magento\Paypal\Block\Express\InContext\Minicart\SmartButton</argument>
        <argument name="template" xsi:type="string">Magento_Paypal::express/in-context/shortcut/button.phtml</argument>
        <argument name="sortOrder" xsi:type="number">10</argument>
    </arguments>
</virtualType>

<type name="Commerce\ShareCart\Model\Checkout\ExpressButtonPool">
    <arguments>
        <argument name="buttons" xsi:type="array">
            <item name="paypal" xsi:type="object">Acme\ShareCart\ExpressButton\PayPal</item>
        </argument>
    </arguments>
</type>
```

Anything more involved implements `Api\Checkout\ExpressButtonInterface` directly. A provider whose module is later uninstalled hides its button rather than fataling, and one that throws during its availability check is logged and skipped — a broken payment integration must not take the cart page down.

---

## Moving the control

It renders through `view/frontend/layout/checkout_cart_index.xml`. Point that at a different container, or add another layout handle, to put it elsewhere. No PHP involved.

---

## Guarantees

### Tokens

- **Uniqueness is a `UNIQUE` index on the digest**, not a PHP check. "Does this
  token exist?" followed by "save it" is a check-then-act race, and losing it
  means the second shopper silently receives the first one's cart. The index also
  fixes the read path: without it, every redemption is a full table scan whose
  cost grows with every share ever created.
- **Allocation is a bounded loop.** Retrying on collision by recursion has no
  floor, so a persistent repository fault recurses until the stack overflows —
  taking the PHP worker with it rather than returning an error.
- **The stored value is a hash.** `varchar(64)`, exactly the width of a SHA-256
  digest, so the unique index over it stays compact.
- **The format check compares `=== 1`.** `preg_match` returns `false` on error,
  and `!false` is `true`, so a negated call marks input as *valid* when the
  backtrack limit overflows.

### The cart itself

- **A link is redeemable only on the store that issued it.** Cross-store
  redemption produces a quote carrying the wrong store's prices, tax and
  currency — a discrepancy that surfaces at payment and nowhere earlier.
- **Snapshots are explicitly guest-owned.** Carrying the sharer's customer id and
  email means the recipient inherits a quote bound to someone else's account.
- **Four restore outcomes, not two.** `RestoreResult` carries a `RestoreOutcome`
  — `Restored`, `NotFound`, `WrongStore`, `Failed` — with the message belonging
  to the outcome rather than the call site. Collapsing a genuine failure into
  "unknown link" tells a shopper whose restore broke that their link is invalid.
- **`deleteById()` loads before it deletes** and throws `NoSuchEntityException`
  for a row that is not there, rather than reporting success for a delete that
  removed nothing.
- **Expired rows are swept nightly**, as one set-based `DELETE`. Without it the
  table grows forever.
- **The token is looked up once per request.** The repository memoises, so
  validating and restoring do not each pay for it.

### What a shopper is told

- **Failures say nothing about internals.** Messages are fixed, translatable
  sentences; exception detail goes to the log.
- **The endpoint is not a token oracle.** "Not found" and "expired" produce the
  same message on the cart page, so a caller cannot tell a wrong token from a
  lapsed one.

---

## Gotchas

- **`getList()` returns `SharedCartSearchResultsInterface`, so the repository builds the result itself.** `Commerce_Foundation`'s `SearchResultBuilder` would otherwise create a generic `Magento\Framework\Api\SearchResults`, which does not implement that interface — a `TypeError` on every call. `SharedCartSearchResults` exists for exactly that, and the preference points at it. If you add another repository here, copy the pattern rather than the annotation.
- **The `quote_id` foreign key cascades.** Magento's quote cleanup removes stale quotes, and a share link whose snapshot quote is deleted goes with it. That is intended, but it means link lifetime is bounded by quote retention as well as by the configured expiry.
- **The token column stores a SHA-256 digest and is sized at 64 characters.** Changing the hash algorithm needs a schema change *and* invalidates every link already in the wild, because the stored digests can no longer be reproduced from the plaintext tokens shoppers hold.
- **An express-checkout button that throws is skipped and logged, not surfaced.** A missing button is therefore a log line rather than a visible error — check `var/log/commerce/share_cart.log` before concluding a payment method is misconfigured.
- **Snapshots are deliberately guest-owned.** The sharer's customer id and email are stripped, so a recipient never inherits a quote bound to someone else's account.

---

## Tests

```bash
M2_VENDOR=/path/to/magento/vendor php ../dev/run-tests.php -c ../dev/phpunit.xml
```

The suite runs against a real Magento installation without being installed into it. `dev/bootstrap.php` builds a PSR-4-only autoloader from that installation's composer map, which is also why it works where the host's own `vendor/autoload.php` is broken.

---

## Rebranding

```bash
php ../bin/rebrand Acme
```

Existing installs also need their data migrated — the table and config section are renamed:

```sql
RENAME TABLE commerce_shared_cart TO acme_shared_cart;
UPDATE core_config_data SET path = REPLACE(path, 'commerce_sharecart/', 'acme_sharecart/')
 WHERE path LIKE 'commerce_sharecart/%';
```

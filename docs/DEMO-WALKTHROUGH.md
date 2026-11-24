# Cecilian Bites: demo walkthrough (about 10 minutes)

A script for presenting the system. It shows the three ideas the proposal is built on:
**pay first, then we cook** · **delivery by building and floor** · **no pranks or scams, without making anyone log in**.

## Before the demo

1. Reset the demo data **just before you start** (orders waiting for payment cancel themselves after 15 minutes):

   ```
   php artisan migrate:fresh --seed
   php artisan serve
   php artisan schedule:work        # second window: cancels unpaid orders on time
   ```

2. Optional, for the "missed order" moment in Part 5: add `ORDER_UNPAID_EXPIRY_MINUTES=1` to `.env`, then `php artisan config:clear`. Remove it afterwards.
3. Open three browser windows (or private windows): **customer**, **cashier**, **administrator/owner**.

All demo accounts use the password `password`.

| Role | Login |
|---|---|
| Overall administrator | `admin@example.com` |
| Owner of Cecilian Café | `owner.kitchen@example.com` |
| Cashier of Cecilian Café | `cashier@example.com` |
| Runner of Cecilian Café | `runner.cecilian-cafe@example.com` |

Demo school IDs (customers do **not** log in; they tap their ID):

| ID | Last name | Who | What to show |
|---|---|---|---|
| `2024-0001` | Dela Cruz | Juan, student | a normal order |
| `2024-0002` | Santos | Maria, student | has 2 missed orders and a waiting order |
| `2024-0003` | Mendoza | Carla, student | **paused** for 3 days |
| `2024-0004` | Lopez | Gabriel, student | the "prankster" |
| `E-0102` | Navarro | Elena, teacher | teachers work the same way |

---

## Part 1: Customer orders with a tap (2 min)

1. Open the site, choose **Cecilian Café**, add two dishes, go to **Checkout**.
2. Type ID `2024-0001` and last name `Dela Cruz`. Leave the name empty. *(On the kiosk a card reader types the ID; on a phone the camera button scans it.)*
3. Choose **Pickup** and **Cash**, then **Place order**.

> **Say:** "No account, no password. The name comes from the school list, so nobody can order as 'Mickey Mouse'."

4. Open **My orders**. The order says **Waiting for payment** and shows when it cancels itself.

> **Say:** "Nothing is cooked yet. A prank order costs the canteen nothing."

## Part 2: Cashier (2 min) · `cashier@example.com`

1. The board has a new first column, **Waiting for payment**. Juan's order says *"Do not prepare yet"* and shows his ID.
2. Maria's order shows a red line: **"2 missed orders before (3 = paused)"**.
3. Open Juan's order and **Collect payment** (cash). It jumps to **Preparing**. Mark it **Ready**, then **Done**.
4. Try the same on an order still waiting: the kitchen can't start it without payment.

> **Say:** "The cashier confirms the payment first. Only then does the kitchen see the order."

## Part 3: Delivery by building and floor (2 min)

1. Customer: new order, choose **Delivery**, building **Annex**, floor **8**, room `804`.
2. The fee appears in the summary: **₱14.00** (the canteen is on the Annex 6th floor, 2 floors away).
3. Try floor **1**: ₱20.00. Try **Main Building** floor 3: ₱29.00.
4. Notice **Cash is switched off** for delivery. Pay with GCash, then enter any reference.
5. Cashier verifies the GCash payment. Mark the order **Ready**.
6. Runner (`runner.cecilian-cafe@example.com`): the order appears as **Annex · Floor 8 · Room 804**. Unpaid delivery orders never show up here.

> **Say:** "Delivery is always paid in advance, so a student runner never carries an unpaid or fake order."

## Part 4: Owner changes the fees (2 min) · `owner.kitchen@example.com`

1. **Delivery & fees**: change the Annex base fee from ₱10 to ₱12 and save. Open **Fee for each floor** to see the new prices.
2. Scroll to **Recent changes**: who changed what, and when.
3. Show the switch **Turn off delivery** (for when no runner is on duty), then turn it back on.
4. **Sales & Reports** shows delivery fees as their own figure.

> **Say:** "Buildings, floors and prices are not fixed in the code. The owner edits them, and every change is on record."

## Part 5: Stopping pranks (2 min) · `admin@example.com`

1. **Order security → School ID at checkout → Require it.**
   Customer: try to order with no ID. It's refused.
2. Customer: ID `2024-0004` with the **wrong** last name. The message says it couldn't match, and is the same message as for an unknown ID. After 5 wrong tries the ID is locked for a few minutes.
3. Customer: ID `2024-0003`, last name `Mendoza`. **"Ordering is paused for this school ID…"**
4. Admin: **Missed orders & automatic pauses** lists Carla. Press **Lift pause**; she can order again.
5. Live strike (needs the 1-minute setting from "Before the demo"): Gabriel (`2024-0004`, `Lopez`) places an order and doesn't pay. After a minute it cancels itself and counts as a **missed order**. Three of these pause his ID for 3 days, **even if he changes phone**.
6. **School IDs** page: show the school list, import by paste, and **Block** an ID.

> **Say:** "Nobody had to do anything: not the cashier, not the school office. The rules run by themselves, and the administrator can undo a mistake."

---

## Questions you may get

**"What if someone uses a friend's ID?"** They also need the friend's last name, wrong guesses are locked out, and the order costs nothing unless paid. Kiosks inside the canteen make it even harder. A card with a built-in secret (NFC) would remove the last gap; that's a future step.

**"What if a customer pays after the order was cancelled?"** Once a customer sends a GCash/Maya reference, the cashier gets 60 minutes to check it, and such orders never count as a missed order. A payment that still arrives after cancellation is refunded by hand.

**"Is it safe for the school's data?"** Customers have no passwords to steal. The school list holds only ID, name and type. Staff passwords are protected, and every fee change is recorded.

**"What does it need to run?"** A normal PHP/Laravel host, a database, and one scheduled task that runs every minute (the demo uses `php artisan schedule:work`).

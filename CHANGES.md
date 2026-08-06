# Kya fix hua — summary

## 1. Do files corrupt thi (project ke andar hi tooti hui thi)
`backend/logs_list.php` aur `backend/update_history_list.php` zip ke andar
hi khaali/corrupt ho chuki thi (null bytes). Inki wajah se admin panel ka
**Activity Log** aur **Update History** tab kabhi load hi nahi hota tha.
Dono ko dobara sahi likh diya gaya hai.

## 2. Token expiry — ab hard-enforced hai
Naya endpoint add kiya: **`backend/user_verify.php`**

- `user_login.php` ko jaanbujh kar waisa hi rakha gaya hai (wo hamesha
  HTTP 200 return karta hai, blocked/expired token ke liye bhi) — kyunki
  `frontend/extend.html` (self-extend page) isi behaviour par depend
  karta hai taaki expired user apna status dekh kar "Extend" button dabaa
  sake. Ise strict banane se extend flow hi tut jaata.
- Isliye alag se `user_verify.php` banaya — ye expired ya blocked token
  ke liye seedha **HTTP 403** return karta hai, koi bypass-able flag
  nahi.

**Aapko apni actual app (jo is zip ke bahar hai) me ye endpoint call
karna hoga:**
```
POST /backend/user_verify.php
Body: { "token": "TKN-XXXX-XXXX-XXXX", "platform": "Android 14 • Pixel 8" }
```
- App khulte hi ek baar call karo — response `403` aaye to app ko
  block/expired screen dikhao, `200` aaye to andar jaane do.
- Fir app chalte rehne tak har 3-5 minute me dobara call karo — isse
  agar admin beech me hi block/expire kar de to turant access cut ho
  jaayega (sirf agli login pe nahi), aur ye online status ko bhi zinda
  rakhega (neeche point 3 dekhein).

## 3. Online status
- `devices` table me pehle se `last_seen` column tha par kahin update
  nahi ho raha tha real-time me. Ab har `user_login.php` ya
  `user_verify.php` call par update hota hai.
- `backend/users_list.php` ab har user/device ke liye `online: true/false`
  bhejta hai (last 3 minute me seen = online — `config.php` me
  `ONLINE_THRESHOLD_MINUTES` se badal sakte ho).
- Admin panel (`frontend/index.html`) me:
  - Dashboard par naya **"Online Now"** stat card
  - Users tab: naam ke aage green/gray dot
  - Devices tab: naya **Status** column (Online/Offline badge)
  - Device modal: har device ke aage dot

## 4. Mobile / phone responsive
- Poore admin panel me hamburger menu drawer add kiya (phone par sidebar
  ab slide-in hoti hai).
- Tables (Users, Devices) ab phone par stacked card-view me dikhti hain
  (horizontal scroll ki bajaye).
- Input fields 16px font size par set kiye (iOS Safari ka auto-zoom-on-
  focus bug fix — pehle 13-14px tha).
- `frontend/extend.html` (user-facing extend page) already kaafi had tak
  mobile-friendly thi, usme bhi wahi iOS zoom fix aur chhoti spacing
  tweaks ki gayi hain.

---

Sab PHP files `php -l` se lint ki gayi hain aur frontend ka poora React/JSX
code Babel se transpile karke aur ek headless (jsdom) mount test se verify
kiya gaya hai — koi syntax ya runtime error nahi mila.

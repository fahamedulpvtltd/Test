# 🎉 Eid Salami Collection System

A premium dark-gold themed web app to collect Eid Salami from friends & family.

---

## 📁 Project Structure

```
eid-salami/
├── index.html        ← Main landing page
├── style.css         ← Dark luxury gold stylesheet
├── script.js         ← Frontend logic (AJAX, animations)
├── upload.php        ← Backend: form submission handler
├── admin.php         ← Admin panel (password protected)
├── data.json         ← JSON "database" (auto-managed)
├── manifest.json     ← PWA manifest
├── .htaccess         ← Apache security rules
├── uploads/          ← Screenshot storage (auto-created)
└── README.md
```

---

## 🚀 Quick Setup

### Requirements
- PHP 7.4+ with GD extension
- Apache or Nginx web server
- Write permissions on project directory

### Steps

1. **Upload** all files to your web server (e.g., `public_html/eid/`)

2. **Set permissions:**
   ```bash
   chmod 755 .
   chmod 644 *.html *.css *.js *.php
   chmod 666 data.json
   mkdir -p uploads && chmod 755 uploads
   ```

3. **Customize** `index.html`:
   - Replace `01XXXXXXXXX` with your actual bKash/Nagad numbers (lines ~45–50)
   - Update your name in the heading if needed

4. **Change admin password** in `admin.php`:
   ```php
   define('ADMIN_PASSWORD', 'your-secure-password-here');
   ```

5. **Visit** `https://yourdomain.com/eid/` to see the live page!

6. **Admin panel:** `https://yourdomain.com/eid/admin.php`

---

## 🔐 Admin Panel Features

- Password-protected login
- View all submissions with screenshots (click to zoom)
- **Approve** → Entry counts in total & leaderboard
- **Reject** → Marked but not counted
- **Delete** → Removes entry + uploaded file
- **Close Collection** toggle → Shows closed banner on site
- Filter by status (All / Pending / Approved / Rejected)
- Live stats: total collected, goal percentage

---

## ⚙️ Configuration Options

| Setting | File | Default |
|---------|------|---------|
| Goal amount | `script.js` line 3 | `10000` BDT |
| Goal amount | `admin.php` line 11 | `10000` BDT |
| Admin password | `admin.php` line 7 | `eid2025admin` |
| Max file size | `upload.php` line 14 | `3 MB` |
| Spam cooldown | `script.js` line ~100 | `10 seconds` |
| Refresh interval | `script.js` last line | `45 seconds` |

---

## 📱 PWA (Install as App)

The app includes a `manifest.json` for PWA support. To enable:
1. Serve over **HTTPS**
2. Add icon files: `icon-192.png` and `icon-512.png` (192×192 and 512×512 px)
3. Users can "Add to Home Screen" from their browser

---

## 🛡️ Security Notes

- `data.json` is blocked from direct browser access via `.htaccess`
- PHP execution is blocked inside `uploads/`
- All inputs are sanitized with `htmlspecialchars` + `strip_tags`
- File MIME type verified server-side with `getimagesize()`
- IP addresses stored as SHA-256 hashes only
- Basic 10-second client-side spam protection

---

## 🎨 Customization

**Colors** — Edit CSS variables at top of `style.css`:
```css
:root {
  --gold-bright: #f5c842;   /* Main gold */
  --bg-deep:     #07050f;   /* Background */
  --accent-pink: #e87c9e;   /* Accent */
}
```

**Fonts** — Replace Google Fonts imports in `index.html`:
- Display: `Cinzel Decorative`
- Body: `Cormorant Garamond`
- UI: `Cairo`

---

## 🌙 Eid Mubarak!

Made with ❤️ — Feel free to customize and share joy!

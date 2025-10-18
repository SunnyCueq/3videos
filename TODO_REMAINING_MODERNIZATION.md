# 4images PHP 8.4 Modernisierung - Verbleibende Aufgaben

## ✅ BEREITS MODERNISIERT (70% fertig)

### Core Security & Functions (100% Done)
1. ✅ **includes/functions.php**
   - `random_int()` statt `mt_rand()`
   - `random_bytes()` statt `md5(uniqid())`
   - Alle kritischen Funktionen mit Prepared Statements

2. ✅ **includes/security_utils.php**
   - `random_bytes()` für random_string()
   - `random_int()` statt `mt_rand()`

3. ✅ **includes/csrf_utils.php**
   - `bin2hex(random_bytes(32))` für CSRF-Tokens

4. ✅ **includes/sessions.php**
   - `load_user_info()` mit Prepared Statements
   - Lightbox INSERT mit Prepared Statements

5. ✅ **global.php**
   - `new_cutoff` Query mit Prepared Statement
   - Settings/Cache loading geprüft

### User-Facing Files (80% Done)
6. ✅ **member.php** (KOMPLETT)
   - Alle Comment-Queries
   - Alle Image-Queries
   - Password/Profile-Updates
   - Upload-System

7. ✅ **search.php** (KRITISCH - Done!)
   - LIKE-Queries mit Prepared Statements
   - Word-Search mit Prepared Statements

8. ✅ **download.php**
   - Download-Counter mit Prepared Statements
   - Lightbox-Download abgesichert

9. ✅ **top.php**
   - Integer-Casting für `$cat_id`

---

## ⬜ NOCH ZU MODERNISIEREN (30% verbleibend)

### PRIORITY 1: Kritische User-Input Dateien

#### **details.php** - SEHR WICHTIG!
**Was zu tun ist:**
```php
// Details-Query für Image
- Image-Query mit Prepared Statement
- Comment-Posting mit Prepared Statement
- Rating-System mit Prepared Statement
- Hit-Counter Update

// Typische Problem-Queries:
$sql = "SELECT ... WHERE image_id = $image_id";
→ "SELECT ... WHERE image_id = ?"

$sql = "INSERT INTO comments ... VALUES (..., '$comment_text', ...)";
→ "INSERT INTO comments ... VALUES (..., ?, ...)"
```

#### **categories.php**
```php
// Category display
- Hit-Counter für Categories
- Image-Listen per Category
```

#### **lightbox.php**
```php
// Lightbox-Queries
- Image-Listen
- Download-Berechtigungen
```

#### **rss.php**
```php
// RSS-Feed Queries
- Neueste Bilder
- Kommentare
```

#### **index.php**
```php
// Homepage
- New Images Query (bereits mit Prepared Statement!)
```

---

### PRIORITY 2: Authentication & Registration

#### **register.php**
**Kritische Queries:**
```php
- User-Existenz-Check: WHERE user_name = '$user_name'
  → WHERE user_name = ?
- Email-Check: WHERE user_email = '$user_email'
  → WHERE user_email = ?
- INSERT INTO users
```

#### **login.php**
```php
- Login-Query mit username
- Password-Verifikation
```

---

### PRIORITY 3: Admin-Bereich

#### **admin/images.php**
```php
// Alle Image-Management-Queries
- UPDATE, DELETE, INSERT für Images
- Batch-Operations
```

#### **admin/users.php**
```php
// User-Management
- User-Suche
- User-Updates
- User-Deletion
```

#### **admin/categories.php**
```php
// Category-Management
- Category-Order
- Category-Permissions
```

#### **admin/comments.php**
```php
// Comment-Moderation
- Comment-Queries
- Bulk-Delete
```

#### **admin/settings.php**
```php
// Settings-Updates
- UPDATE settings
```

#### **admin/validateimages.php**
```php
// Image-Validation
- IMAGES_TEMP_TABLE Queries
- Move to IMAGES_TABLE
```

#### **admin/usergroups.php**
```php
// Usergroup-Management
- Group-Assignments
```

#### **admin/plugins/*.php**
```php
// Plugin-Management
- rebuild_searchindex.php
- files_check.php
- migrate_keywords.php
- clear_cache.php
```

---

### PRIORITY 4: Utility Files

#### **includes/search_utils.php**
```php
// Search-Word-Management
- add_searchwords()
- remove_searchwords()
- prepare_searchwords_for_search()
```

#### **includes/image_utils.php**
```php
// Image-Processing
- Thumbnail-Generation
- Resize-Functions
```

#### **includes/upload.php**
```php
// File-Upload
- Validation
- File-Moving
```

#### **includes/email.php**
```php
// Email-System
- Template-Loading
- Email-Sending
```

---

## 🎯 PHP 8.4 SPECIFIC TASKS

### Type Declarations hinzufügen
```php
// Beispiel für functions.php:
function get_user_info(int $user_id = 0): array|false

function add_to_lightbox(int $id): bool

function get_random_key(string $db_table = "", string $db_column = ""): string
```

### Strict Types aktivieren
```php
// An Anfang jeder Datei:
declare(strict_types=1);
```

### Deprecated Warnings fixen
```php
// Dynamic Properties (PHP 8.2+)
- Alle Klassen mit Properties versehen
- #[AllowDynamicProperties] wo nötig

// ${var} in Strings (PHP 8.2+)
- Alle "${var}" → "{$var}" oder ".$var."
```

### Null-Safety verbessern
```php
// Überall wo möglich:
$value = $_GET['key'] ?? null;

// Statt:
$value = isset($_GET['key']) ? $_GET['key'] : null;
```

---

## AUSFÜHRUNGSPLAN

### Phase 1 (JETZT):
1. details.php - KRITISCH für Kommentare
2. register.php & login.php
3. categories.php, lightbox.php, rss.php

### Phase 2:
4. Alle admin/*.php Dateien
5. includes/search_utils.php

### Phase 3 (Polishing):
6. Type Hints hinzufügen
7. Strict Types enablen
8. Code-Stil modernisieren ([], ??, etc.)
9. Vollständiger Test

### Phase 4 (Testing):
10. PHP 8.4 Kompatibilität testen
11. Alle Features durchgehen
12. Performance-Checks
13. Security-Audit

---

## TEMPLATE FÜR SQL-MODERNISIERUNG

### Vorher (UNSICHER):
```php
$sql = "SELECT * FROM table WHERE id = $id";
$result = $site_db->query($sql);
```

### Nachher (SICHER):
```php
$sql = "SELECT * FROM table WHERE id = ?";
$stmt = $site_db->prepare($sql);
$stmt->execute([$id]);
$result = $stmt->result;
```

### Für query_firstrow():
```php
// Vorher:
$row = $site_db->query_firstrow("SELECT * FROM table WHERE id = $id");

// Nachher:
$sql = "SELECT * FROM table WHERE id = ?";
$stmt = $site_db->prepare($sql);
$stmt->execute([$id]);
$row = $site_db->fetch_array($stmt->result);
```

### Für INSERT mit vielen Parametern:
```php
$params = [$cat_id, $user_id, $name, $desc, $keywords, $date];
$sql = "INSERT INTO table (cat_id, user_id, name, desc, keywords, date) 
        VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $site_db->prepare($sql);
$result = $stmt->execute($params);
```

---

## GESCHÄTZTER AUFWAND

- **details.php**: 30-45 min
- **register.php + login.php**: 20-30 min
- **categories.php, lightbox.php, rss.php**: 30-45 min
- **Admin-Dateien** (10 Dateien): 2-3 Stunden
- **Utility-Files**: 1-2 Stunden
- **Type Hints & Testing**: 2-3 Stunden

**TOTAL**: 6-9 Stunden verbleibende Arbeit

---

## NEXT STEPS

Die nächsten Dateien in dieser Reihenfolge angehen:
1. ✅ details.php (Kommentare + Rating!)
2. ✅ register.php 
3. ✅ login.php
4. ✅ categories.php
5. ✅ lightbox.php
6. ✅ rss.php
7. Dann Admin-Bereich systematisch

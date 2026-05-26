# 🔐 Magento / PHP Security Verification Checklist (PolyShell & Malware)

## 🚨 1. Find Newly Created / Modified Files (Last 2 Days)
```bash
find /var/www/html -type f -mtime -2 -ls
```
- Focus on:
  - /pub/media/
  - /var/
  - /tmp/

## 🚨 2. Detect PHP Files in Upload Locations (CRITICAL)
```bash
find /var/www/html/pub/media -type f \( -name "*.php" -o -name "*.phtml" -o -name "*.phar" \)
find /tmp -type f \( -name "*.php" -o -name "*.phtml" -o -name "*.sh" \)
```
- Any result here = HIGHLY SUSPICIOUS

## 🚨 3. Detect Obfuscated / Malicious Code Patterns
```bash
grep -R --line-number --color "eval\|base64_decode\|gzinflate\|str_rot13\|shell_exec\|exec\|passthru\|system\|assert" /var/www/html
grep -R "base64_decode" /var/www/html
grep -R "eval(" /var/www/html
```

## 🚨 4. Detect Suspicious File Names
```bash
find /var/www/html -type f -regex '.*\.\(jpg\|png\|gif\)\.php'
find /var/www/html -type f -iname "*shell*"
find /var/www/html -type f -iname "*cmd*"
```

## 🚨 5. Check Writable Files
```bash
find /var/www/html -type f -perm -o+w
```

## 🚨 6. Verify Cron Jobs
```bash
crontab -l
sudo crontab -l
ls -la /etc/cron*
```

## 🚨 7. Check Running PHP Processes
```bash
ps aux | grep php
```

## 🚨 8. Check Active Network Connections
```bash
sudo ss -plant | grep ESTABLISHED
```

## 🚨 9. Magento Upload Validation Check
```bash
grep -R "fileUploader" app/code vendor
grep -R "tmp_name" app/code vendor
```

## 🚨 10. Block PHP Execution in Media Folder
### Apache
```bash
cat /var/www/html/pub/media/.htaccess
```

### Nginx
```bash
grep -R "media" /etc/nginx/
```

## 🚨 11. Check Core File Changes
```bash
find vendor/ -type f -mtime -2
```

## 🚨 12. Recently Modified PHP Files
```bash
find /var/www/html -type f -name "*.php" -mtime -2
```

## 🚨 13. Hidden Backdoor Files
```bash
find /var/www/html -type f -name ".*.php"
```

## 🚨 14. Malware Scan (Optional)
```bash
clamscan -r /var/www/html
```

## 🚨 15. Check Logs for Exploits
```bash
grep -i "upload" /var/log/nginx/access.log
grep -i "post" /var/log/nginx/access.log
```

## 🚨 16. Large Suspicious Files
```bash
find /var/www/html -type f -size +5M
```

## 🚨 17. Symlink Check
```bash
find /var/www/html -type l -ls
```

## 🚨 18. File Ownership Check
```bash
find /var/www/html -type f -exec ls -l {} \; | grep root
```

## 🔥 If Suspicious File Found
```bash
mv suspicious.php /tmp/
cat suspicious.php
```

## 🧠 Key Focus Areas
- /pub/media/
- /tmp/
- Recently modified .php files
- Files with eval(base64_decode())

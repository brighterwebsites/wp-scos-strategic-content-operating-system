# CyberPanel IP Address Fix Guide

## Server Status

### ✅ host2.bweb1.com.au:8090
- **Dashboard IP:** 70.36.114.234
- **Actual IP:** 70.36.114.234
- **Status:** CORRECT - No action needed

### ❌ host.bweb1.com.au:8090
- **Dashboard IP:** 192.154.100.199 (OLD)
- **Actual IP:** 70.36.114.232 (NEW)
- **Status:** NEEDS FIX

---

## BEFORE YOU START - BACKUP!

```bash
# SSH into host.bweb1.com.au as root
ssh root@host.bweb1.com.au

# Create backup directory
mkdir -p /root/cyberpanel-backups/$(date +%Y%m%d)
cd /root/cyberpanel-backups/$(date +%Y%m%d)

# Backup critical files
cp /usr/local/CyberCP/CyberCP/settings.py settings.py.backup
cp /usr/local/lsws/conf/httpd_config.conf httpd_config.conf.backup
cp -r /usr/local/lsws/conf/vhosts/ vhosts_backup/

# Backup database
mysqldump -u root -p cyberpanel > cyberpanel_db_backup.sql

echo "Backups created in /root/cyberpanel-backups/$(date +%Y%m%d)"
ls -lah
```

---

## STEP 1: Update CyberPanel Main IP

### Option A: Use CyberPanel CLI (Recommended)

```bash
# Change main IP address
cyberpanel changeServerIP --oldIP 192.154.100.199 --newIP 70.36.114.232

# This should update:
# - CyberPanel settings
# - Virtual hosts
# - SSL certificates
# - DNS zones
```

### Option B: Manual Database Update (if CLI fails)

```bash
# Access MySQL
mysql -u root -p

# Use CyberPanel database
USE cyberpanel;

# Check current IP entries
SELECT * FROM websiteFunctions_websites WHERE ipAddress = '192.154.100.199';

# Update IP address
UPDATE websiteFunctions_websites SET ipAddress = '70.36.114.232' WHERE ipAddress = '192.154.100.199';

# Verify changes
SELECT * FROM websiteFunctions_websites WHERE ipAddress = '70.36.114.232';

# Exit MySQL
EXIT;
```

---

## STEP 2: Update OpenLiteSpeed Configuration

### Check and Update Main Server Config

```bash
# Edit main httpd config
nano /usr/local/lsws/conf/httpd_config.conf

# Find and replace:
# OLD: 192.154.100.199
# NEW: 70.36.114.232

# Search for:
grep -n "192.154.100.199" /usr/local/lsws/conf/httpd_config.conf

# Use sed to replace (CAREFUL - check first!)
sed -i.bak 's/192.154.100.199/70.36.114.232/g' /usr/local/lsws/conf/httpd_config.conf

# Verify changes
diff /usr/local/lsws/conf/httpd_config.conf.bak /usr/local/lsws/conf/httpd_config.conf
```

### Update All Virtual Host Configs

```bash
# Find all vhost configs with old IP
grep -r "192.154.100.199" /usr/local/lsws/conf/vhosts/

# Replace in all vhost files
find /usr/local/lsws/conf/vhosts/ -type f -name "*.conf" -exec sed -i.bak 's/192.154.100.199/70.36.114.232/g' {} \;

# Verify changes
grep -r "192.154.100.199" /usr/local/lsws/conf/vhosts/
# Should return: (no results)

grep -r "70.36.114.232" /usr/local/lsws/conf/vhosts/
# Should show new IP in configs
```

---

## STEP 3: Update CyberPanel Settings

```bash
# Edit CyberPanel settings
nano /usr/local/CyberCP/CyberCP/settings.py

# Find SERVER_IP and update:
# OLD: SERVER_IP = '192.154.100.199'
# NEW: SERVER_IP = '70.36.114.232'

# Or use sed:
sed -i.bak "s/SERVER_IP = '192.154.100.199'/SERVER_IP = '70.36.114.232'/g" /usr/local/CyberCP/CyberCP/settings.py

# Verify
grep "SERVER_IP" /usr/local/CyberCP/CyberCP/settings.py
```

---

## STEP 4: Update SSL Certificates

```bash
# List all sites
ls -la /usr/local/lsws/conf/vhosts/

# For EACH site, check SSL config
# Example for bweb1.com.au:
nano /usr/local/lsws/conf/vhosts/bweb1.com.au/vhconf.conf

# Look for SSL listener bindings
# Update IP from 192.154.100.199:443 to 70.36.114.232:443

# Or do it automatically for all:
find /usr/local/lsws/conf/vhosts/ -type f -name "vhconf.conf" -exec sed -i 's/192.154.100.199:443/70.36.114.232:443/g' {} \;
find /usr/local/lsws/conf/vhosts/ -type f -name "vhconf.conf" -exec sed -i 's/192.154.100.199:80/70.36.114.232:80/g' {} \;
```

---

## STEP 5: Update Email/DNS (if using CyberPanel DNS/Email)

### Update Email Server IP

```bash
# Check Postfix main config
grep "192.154.100.199" /etc/postfix/main.cf

# Update if found
sed -i.bak 's/192.154.100.199/70.36.114.232/g' /etc/postfix/main.cf

# Check Dovecot
grep "192.154.100.199" /etc/dovecot/dovecot.conf

# Update if found
sed -i.bak 's/192.154.100.199/70.36.114.232/g' /etc/dovecot/dovecot.conf
```

### Update PowerDNS (if using CyberPanel DNS)

```bash
# Access PowerDNS database
mysql -u root -p powerdns

# Check A records
SELECT * FROM records WHERE content = '192.154.100.199';

# Update A records
UPDATE records SET content = '70.36.114.232' WHERE content = '192.154.100.199';

# Verify
SELECT * FROM records WHERE content = '70.36.114.232';

EXIT;
```

---

## STEP 6: Restart All Services

```bash
# Restart OpenLiteSpeed
systemctl restart lsws

# Verify it started
systemctl status lsws

# Restart CyberPanel
systemctl restart lscpd

# Verify it started
systemctl status lscpd

# Restart email services (if using)
systemctl restart postfix
systemctl restart dovecot

# Restart DNS (if using PowerDNS)
systemctl restart pdns
```

---

## STEP 7: Verify Changes

### Check CyberPanel Dashboard

```bash
# Open in browser:
https://host.bweb1.com.au:8090

# Log in and check:
# 1. Dashboard should show: 70.36.114.232
# 2. Go to List Websites → Check each site's IP
```

### Verify OpenLiteSpeed

```bash
# Check listeners
/usr/local/lsws/bin/lshttpd -V

# Check if old IP is still referenced anywhere
grep -r "192.154.100.199" /usr/local/lsws/conf/

# Should return: (no results)
```

### Test Site Access

```bash
# Test each site loads
curl -I http://bweb1.com.au
curl -I https://bweb1.com.au
curl -I http://review.bweb1.com.au
curl -I https://review.bweb1.com.au

# Check all your sites...
```

### Verify Database

```bash
mysql -u root -p -e "USE cyberpanel; SELECT domain, ipAddress FROM websiteFunctions_websites;"

# All should show: 70.36.114.232
```

---

## STEP 8: Update Firewall/CSF (if needed)

```bash
# Check if old IP is in CSF allow list
grep "192.154.100.199" /etc/csf/csf.conf
grep "192.154.100.199" /etc/csf/csf.allow

# Update if found
sed -i 's/192.154.100.199/70.36.114.232/g' /etc/csf/csf.conf
sed -i 's/192.154.100.199/70.36.114.232/g' /etc/csf/csf.allow

# Restart CSF
csf -r
```

---

## TROUBLESHOOTING

### If sites don't load after change:

```bash
# Check OpenLiteSpeed error logs
tail -100 /usr/local/lsws/logs/error.log

# Check which IP OpenLiteSpeed is listening on
netstat -tlnp | grep lshttpd

# Should show:
# 70.36.114.232:80
# 70.36.114.232:443
```

### If CyberPanel dashboard is inaccessible:

```bash
# Check CyberPanel logs
tail -100 /usr/local/CyberCP/logs/access.log
tail -100 /usr/local/CyberCP/logs/error.log

# Restart CyberPanel
systemctl restart lscpd
```

### If SSL breaks:

```bash
# Reissue SSL for each site via CyberPanel:
# SSL → Manage SSL → Select site → Issue SSL
```

---

## VERIFICATION CHECKLIST

After completing all steps, verify:

- [ ] CyberPanel dashboard shows 70.36.114.232
- [ ] All websites in List Websites show 70.36.114.232
- [ ] All sites load via HTTP (port 80)
- [ ] All sites load via HTTPS (port 443)
- [ ] No references to 192.154.100.199 in configs: `grep -r "192.154.100.199" /usr/local/lsws/conf/`
- [ ] No references to 192.154.100.199 in database
- [ ] Email works (if using CyberPanel email)
- [ ] DNS resolves correctly (if using CyberPanel DNS)
- [ ] OpenLiteSpeed listening on new IP: `netstat -tlnp | grep lshttpd`
- [ ] No errors in logs: `tail -100 /usr/local/lsws/logs/error.log`

---

## QUICK REFERENCE COMMANDS

```bash
# One-liner to check all old IP references
grep -r "192.154.100.199" /usr/local/lsws/conf/ /usr/local/CyberCP/ /etc/postfix/ /etc/dovecot/ /etc/csf/ 2>/dev/null

# One-liner to restart all services
systemctl restart lsws lscpd postfix dovecot pdns csf

# Check all services status
systemctl status lsws lscpd postfix dovecot pdns csf
```

---

## NEXT STEPS

After IP is fixed on host.bweb1.com.au:
1. ✅ Verify all sites accessible
2. ✅ Update external DNS A records (at domain registrar)
3. ✅ Update rDNS at VPS provider (70.36.114.232 → host.bweb1.com.au)
4. ✅ Proceed with security audit

---

**Created:** 2025-11-29
**Server:** host.bweb1.com.au (70.36.114.232)
**Old IP:** 192.154.100.199
**New IP:** 70.36.114.232

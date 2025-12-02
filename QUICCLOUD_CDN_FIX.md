# URGENT: mensfinanceadvice.com.au QUIC.cloud Fix

**Domain:** mensfinanceadvice.com.au
**Error:** QUIC.cloud CDN cannot reach origin server
**Status:** DOWN - Need immediate fix

---

## QUICK DIAGNOSIS - Do These First!

### Step 1: Which Server is This Site On?

```bash
# SSH into host.bweb1.com.au
ssh root@host.bweb1.com.au
ls /home/ | grep mensfinanceadvice

# If not found, try host2:
ssh root@host2.bweb1.com.au
ls /home/ | grep mensfinanceadvice
```

**Once you find which server:**

### Step 2: Check if Site Works via Direct IP

```bash
# Replace XX.XX.XX.XX with the server's IP
# host.bweb1.com.au = 70.36.114.232
# host2.bweb1.com.au = 70.36.114.234

# Test direct IP access (bypasses QUIC.cloud)
curl -H "Host: mensfinanceadvice.com.au" http://70.36.114.232
curl -H "Host: mensfinanceadvice.com.au" https://70.36.114.232 -k

# OR test on your computer:
# Add to C:\Windows\System32\drivers\etc\hosts (Windows)
# Add to /etc/hosts (Mac/Linux)
70.36.114.232 mensfinanceadvice.com.au

# Then browse: http://mensfinanceadvice.com.au
```

**Result:**
- ✅ Site loads via direct IP = **Problem is QUIC.cloud → Origin connection**
- ❌ Site doesn't load via direct IP = **Problem is on the server itself**

---

## If Site Loads via Direct IP (QUIC.cloud Issue)

### Fix 1: Update Origin IP in QUIC.cloud Dashboard

**CRITICAL:** If this site is on **host.bweb1.com.au**, the IP changed!

1. Log into QUIC.cloud: https://quic.cloud/
2. Go to **CDN** → **Domains**
3. Find **mensfinanceadvice.com.au**
4. Check **Origin IP** setting:
   - ❌ If it shows: `192.154.100.199` (OLD IP - WRONG!)
   - ✅ Should be: `70.36.114.232` (NEW IP)
5. Update to correct IP
6. Save and wait 2-3 minutes

### Fix 2: Whitelist QUIC.cloud IPs in Firewall

```bash
# SSH into the server where mensfinanceadvice.com.au lives

# Check if CSF is blocking QUIC.cloud
tail -100 /var/log/lfd.log | grep -E "103.231|147.185"

# Add QUIC.cloud IPs to whitelist
nano /etc/csf/csf.allow

# Add these lines at the bottom:
###############################################################################
# QUIC.cloud CDN Edge Nodes - Allow connections to origin
###############################################################################
tcp|in|d=80|s=103.231.136.0/22
tcp|in|d=443|s=103.231.136.0/22
tcp|in|d=80|s=147.185.132.0/22
tcp|in|d=443|s=147.185.132.0/22
tcp|in|d=80|s=103.231.140.0/22
tcp|in|d=443|s=103.231.140.0/22

# Save and restart CSF
csf -r

# Verify rules added
csf -g 103.231.136.1
```

### Fix 3: Check OpenLiteSpeed is Running

```bash
# Check status
systemctl status lsws

# If not running:
systemctl start lsws
systemctl enable lsws

# Check ports
netstat -tlnp | grep lshttpd
# Should show:
# 0.0.0.0:80 LISTEN
# 0.0.0.0:443 LISTEN
```

### Fix 4: Verify SSL Certificate

```bash
# Check SSL for this domain
ls -la /etc/letsencrypt/live/ | grep mensfinanceadvice

# Check virtual host SSL config
cat /usr/local/lsws/conf/vhosts/mensfinanceadvice.com.au/vhconf.conf | grep -A5 "vhssl"

# If SSL is broken, reissue via CyberPanel:
# CyberPanel → SSL → Manage SSL → mensfinanceadvice.com.au → Issue SSL
```

---

## If Site DOESN'T Load via Direct IP (Server Issue)

### Check 1: OpenLiteSpeed Running?

```bash
systemctl status lsws
systemctl restart lsws
```

### Check 2: Virtual Host Exists?

```bash
ls -la /usr/local/lsws/conf/vhosts/ | grep mensfinanceadvice
cat /usr/local/lsws/conf/vhosts/mensfinanceadvice.com.au/vhconf.conf
```

### Check 3: DocumentRoot Exists?

```bash
ls -la /home/mensfinanceadvice.com.au/public_html/
```

### Check 4: File Permissions?

```bash
# Check ownership
ls -la /home/mensfinanceadvice.com.au/

# Should be owned by site user, not root
chown -R mensfinanceadvice:mensfinanceadvice /home/mensfinanceadvice.com.au/public_html/
```

### Check 5: Check Error Logs

```bash
# OpenLiteSpeed error log
tail -100 /usr/local/lsws/logs/error.log

# Virtual host error log
tail -100 /usr/local/lsws/conf/vhosts/mensfinanceadvice.com.au/logs/error.log
```

---

## Quick Commands Summary

```bash
# 1. Find which server
ssh root@host.bweb1.com.au
ls /home/ | grep mensfinanceadvice

# 2. Test direct IP (if on host.bweb1.com.au)
curl -H "Host: mensfinanceadvice.com.au" http://70.36.114.232 -I

# 3. Check services
systemctl status lsws
netstat -tlnp | grep :443

# 4. Check firewall
csf -l | grep -E "103.231|147.185"

# 5. Add QUIC.cloud IPs to CSF allow
nano /etc/csf/csf.allow
# (add IPs from Fix 2 above)
csf -r

# 6. Check QUIC.cloud dashboard
# Update origin IP if needed
```

---

## Most Likely Cause (Based on Your Setup)

**If mensfinanceadvice.com.au is on host.bweb1.com.au:**

The origin IP in QUIC.cloud is pointing to the **OLD IP** (192.154.100.199) instead of the **NEW IP** (70.36.114.232)!

**Fix:**
1. Log into QUIC.cloud
2. Update origin IP to: `70.36.114.232`
3. Wait 2-3 minutes
4. Test: https://mensfinanceadvice.com.au

---

## After Site is Back Up

Run these to confirm everything:

```bash
# Check site loads
curl -I https://mensfinanceadvice.com.au

# Check SSL
openssl s_client -connect mensfinanceadvice.com.au:443 -servername mensfinanceadvice.com.au < /dev/null

# Check DNS
dig mensfinanceadvice.com.au +short
nslookup mensfinanceadvice.com.au
```

---

**Next:** Tell me which server this site is on and I'll give you the exact commands!

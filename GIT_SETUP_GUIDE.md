# Git Setup Guide - Multi-Repo Strategy

**Last Updated**: 2025-01-XX  
**Purpose**: Guide for setting up Git repositories and deployment workflow

---

## Repository Structure Overview

### Two Separate Repositories

1. **`mu-brighter-support-main/`** - Code Repository
   - **Purpose**: WordPress MU plugin code only
   - **GitHub**: https://github.com/brighterwebsites/mu-brighter-support
   - **Contains**: PHP code, assets, essential config files
   - **Excludes**: All documentation (see .gitignore)

2. **`brighter-frameworks-docs/`** - Documentation Repository
   - **Purpose**: Technical documentation, frameworks, specifications
   - **GitHub**: (to be created or existing)
   - **Contains**: All .md files, frameworks, implementation guides
   - **Excludes**: Code files

---

## Current Status

### ✅ What's Working
- `brighter-frameworks-docs/` is a Git repository (needs ownership fix)
- Documentation structure is organized
- Code structure is ready for Git

### ⚠️ What Needs Setup
- `mu-brighter-support-main/` is NOT a Git repository yet
- `brighter-frameworks-docs/` has ownership issues (Windows file system)
- GitHub sync not configured
- Deployment workflow needs verification

---

## Setup Instructions

### Step 1: Fix brighter-frameworks-docs Git Ownership

```powershell
cd e:\GIT_REPOS\brighter-frameworks-docs
git config --global --add safe.directory E:/GIT_REPOS/brighter-frameworks-docs
git status
```

**Why**: Windows file systems don't record ownership, Git needs an exception.

---

### Step 2: Initialize mu-brighter-support-main Git Repository

```powershell
cd e:\GIT_REPOS\mu-brighter-support-main

# Initialize git
git init

# Add all files (respects .gitignore)
git add .

# Create initial commit
git commit -m "Initial commit: Modular SCOS architecture (Phase 1-2 complete)"

# Add remote (replace with your GitHub repo URL)
git remote add origin https://github.com/brighterwebsites/mu-brighter-support.git

# Check remote
git remote -v
```

---

### Step 3: Connect to GitHub

#### Option A: Push to Existing Repository (Recommended)

If the GitHub repo exists but is out of sync:

```powershell
cd e:\GIT_REPOS\mu-brighter-support-main

# Fetch existing content
git fetch origin

# Check what's different
git log origin/main..HEAD  # Your local commits
git log HEAD..origin/main  # Remote commits you don't have

# If remote has important history, merge:
git pull origin main --allow-unrelated-histories

# If starting fresh (remote is outdated), force push:
# WARNING: This overwrites remote. Only do if remote is outdated.
git push -u origin main --force
```

#### Option B: Create New Repository

If starting fresh:

1. Create new repo on GitHub: https://github.com/brighterwebsites/mu-brighter-support
2. Don't initialize with README (we have one)
3. Follow Step 2 above, then:

```powershell
git push -u origin main
```

---

### Step 4: Verify .gitignore is Working

```powershell
cd e:\GIT_REPOS\mu-brighter-support-main

# Check what will be committed (should NOT show .md files except README.md)
git status

# Verify specific files are ignored
git check-ignore IMPLEMENTATION_ROADMAP.md
git check-ignore docs/
git check-ignore MODULE_REFERENCE.md
# These should all return the file path (confirming they're ignored)
```

---

## Multi-Repo Strategy for Cursor Workspace

### Can One Cursor Workspace Have Multiple Git Repos?

**Answer**: Yes, but each folder needs its own `.git` directory.

**Current Setup**:
- `E:\GIT_REPOS\` is your workspace root
- `E:\GIT_REPOS\mu-brighter-support-main\` will have its own `.git/`
- `E:\GIT_REPOS\brighter-frameworks-docs\` has its own `.git/`

**How Cursor Handles This**:
- Cursor can work with multiple Git repos in the same workspace
- Each repo is tracked independently
- Git commands run in the appropriate directory context
- Source control panel shows status for the active file's repo

**Best Practice**:
- Keep repos separate (current structure is correct)
- Use clear folder names
- Each repo has its own purpose (code vs docs)

---

## Deployment Strategy

### What Gets Deployed

**Deployed to Production** (via `deploy.sh`):
- ✅ All PHP code (`brighter-core/`, `site-essentials/`)
- ✅ Assets (CSS, JS, images)
- ✅ Essential files (`brighter-core-loader.php`, `site-essentials.php`)
- ✅ Deployment scripts (`deploy.sh`, `deploy-cleanup.sh`)
- ✅ `README.md` and `LICENSE` (for GitHub visibility)

**NOT Deployed** (excluded by .gitignore):
- ❌ All documentation (`.md` files except README.md)
- ❌ Development notes
- ❌ Implementation roadmaps
- ❌ Technical architecture docs
- ❌ History/archive folders

### Deployment Workflow

1. **Local Development** (Cursor workspace)
   - Edit code in `mu-brighter-support-main/`
   - Reference docs in `brighter-frameworks-docs/`
   - Test locally

2. **Commit to Git**
   ```powershell
   cd e:\GIT_REPOS\mu-brighter-support-main
   git add .
   git commit -m "Description of changes"
   git push origin main
   ```

3. **Deploy to Production**
   - SSH to server
   - Run `deploy.sh` (pulls from GitHub)
   - Script automatically excludes documentation (via .gitignore)

---

## Documentation Access Strategy

### Single Source of Truth (SSOT)

**Documentation Location**: `brighter-frameworks-docs/`

**Structure**:
```
brighter-frameworks-docs/
├── frameworks/          # Framework definitions (ALTC, WFB, SCOS)
├── implementation/      # Implementation guides, specs
├── technical/           # Technical specifications (CAR, CAM, etc.)
├── Playbooks/          # Operational playbooks
└── GLOSSARY.md         # Terminology reference
```

**Code Repository**: `mu-brighter-support-main/`
- Contains code only
- References documentation (mentions where to find docs)
- No duplicate documentation

### For AI/Coding Tools (Claude, GPT, Cursor)

**Access Pattern**:
1. **Code work**: Use `mu-brighter-support-main/` files
2. **Documentation reference**: Use `brighter-frameworks-docs/` files
3. **Both available**: Cursor workspace includes both folders

**Best Practice**:
- When coding, reference docs from `brighter-frameworks-docs/`
- Don't duplicate docs in code repo
- Update docs in `brighter-frameworks-docs/` only

---

## GitHub Repository Recommendations

### Option 1: Update Existing Repo (Recommended)

**Pros**:
- Preserves existing GitHub history
- Existing deployments can continue
- No need to update deployment scripts

**Cons**:
- Need to merge/resolve conflicts if remote has changes
- May need to clean up old files

**Steps**:
1. Initialize local Git repo
2. Add remote: `git remote add origin https://github.com/brighterwebsites/mu-brighter-support.git`
3. Pull existing content: `git pull origin main --allow-unrelated-histories`
4. Resolve any conflicts
5. Push: `git push origin main`

### Option 2: Create Fresh Repo

**Pros**:
- Clean start
- No conflicts
- Only current code structure

**Cons**:
- Loses GitHub history
- Need to update deployment scripts with new repo URL
- Need to update any webhooks/integrations

**Steps**:
1. Create new repo on GitHub
2. Initialize local Git repo
3. Add remote
4. Push: `git push -u origin main`

---

## Verification Checklist

After setup, verify:

- [ ] `mu-brighter-support-main/` is a Git repository (`git status` works)
- [ ] `brighter-frameworks-docs/` Git ownership fixed (`git status` works)
- [ ] `.gitignore` excludes documentation files
- [ ] `git status` shows only code files (no .md files except README.md)
- [ ] Remote is connected (`git remote -v` shows GitHub URL)
- [ ] Can push to GitHub (`git push` works)
- [ ] Deployment script works (tests on staging first)

---

## Next Steps After Git Setup

1. ✅ Git repositories configured
2. ✅ Documentation separated from code
3. ✅ Ready to start Phase 4: Content Modules
4. 📋 Complete immediate fixes (see IMPLEMENTATION_ROADMAP.md Phase 4)
5. 📋 Begin Content Strategy Fields migration

---

## Troubleshooting

### Issue: "fatal: not a git repository"
**Solution**: Run `git init` in the directory

### Issue: "dubious ownership" error
**Solution**: Run `git config --global --add safe.directory <path>`

### Issue: Documentation files showing in `git status`
**Solution**: Check `.gitignore` is in root directory and syntax is correct

### Issue: Deployment includes documentation
**Solution**: Verify `.gitignore` is committed and pushed to GitHub

### Issue: Can't push to GitHub
**Solution**: 
- Check authentication (SSH key or personal access token)
- Verify remote URL: `git remote -v`
- Check branch name: `git branch` (should be `main`)

---

## Related Documents

- **IMPLEMENTATION_ROADMAP.md** - Phase 4 requirements
- **DEPLOYMENT.md** - Deployment procedures
- **DEVELOPMENT_GUIDE.md** - Development protocols
- **brighter-frameworks-docs/** - All technical documentation

---

**End of Git Setup Guide**


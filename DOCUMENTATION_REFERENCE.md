# Documentation Reference Guide

**Purpose**: Quick reference for where to find documentation  
**Last Updated**: 2025-01-XX

---

## ⚠️ Important: Documentation Location Change

**All technical documentation has moved to**: `brighter-frameworks-docs/`

**This repository** (`mu-brighter-support-main/`) now contains:
- ✅ Code only (PHP, CSS, JS)
- ✅ Essential config files
- ✅ Deployment scripts
- ❌ No documentation (excluded via .gitignore)

---

## Documentation Locations

### Primary Documentation Repository

**Path**: `E:\GIT_REPOS\brighter-frameworks-docs\`

### Key Documents by Category

#### Framework Definitions
- **ALTC Framework**: `frameworks/ALTC-Framework-Definitions.md`
- **WFB Blueprint**: `frameworks/WFB-Blueprint-Overview.md`
- **Relationship Map**: `frameworks/relationship-map.md`
- **Terminology**: `GLOSSARY.md` and `Proprietary-Terminology-Reference.md`

#### Implementation Guides
- **SCOS Architecture**: `implementation/SCOS-architecture.md`
- **SCOS Overview**: `implementation/SCOS-Conceptual-Overview.md`
- **AI-First Reference**: `implementation/AI-First-Implementation-Reference.md`
- **Module Specifications**: `implementation/module-specifications/`

#### Technical Specifications
- **Proof Library**: `technical/01-proof-library.md`
- **CAR Schema**: `technical/02-CAR-schema.md`
- **CAM Structure**: `technical/03-CAM-structure.md`
- **LLM.txt Format**: `technical/04-llm-txt-format.md`
- **Page Directives**: `technical/05-page-directives.md`
- **Integration Map**: `technical/06-integration-map.md`

#### Playbooks
- **Operational Playbooks**: `Playbooks/`
- **Archived Playbooks**: `Playbooks/Archived/`

#### Archive
- **Historical Documents**: `archive/`

---

## Code Repository Documentation (This Repo)

### Essential Files (Kept in Code Repo)

- **README.md** - Basic project overview (for GitHub)
- **LICENSE** - GPL-3.0 license
- **DEPLOYMENT.md** - Deployment procedures
- **GIT_SETUP_GUIDE.md** - This Git setup guide
- **DOCUMENTATION_REFERENCE.md** - This file

### Development Files (Local Only, Not in Git)

These files exist locally but are excluded from Git:
- `IMPLEMENTATION_ROADMAP.md` - Development roadmap
- `MODULE_REFERENCE.md` - Module status
- `TECHNICAL_ARCHITECTURE.md` - System architecture
- `DEVELOPMENT_GUIDE.md` - Development protocols
- `PRODUCT_STRATEGY.md` - Product vision
- All files in `docs/history/`

**Why Excluded**: These are development/implementation docs, not needed in production.

---

## For AI/Coding Tools

### When Working on Code

**Use These Locations**:
- Code: `mu-brighter-support-main/`
- Reference docs: `brighter-frameworks-docs/`

**Example**:
- Implementing ALTC system → Reference `brighter-frameworks-docs/frameworks/ALTC-Framework-Definitions.md`
- Writing module code → Use `mu-brighter-support-main/site-essentials/Modules/`

### When Updating Documentation

**Update These Locations**:
- Framework docs → `brighter-frameworks-docs/frameworks/`
- Implementation guides → `brighter-frameworks-docs/implementation/`
- Technical specs → `brighter-frameworks-docs/technical/`

**Don't Update**:
- Documentation in `mu-brighter-support-main/` (it's excluded from Git)

---

## Quick Reference Map

```
E:\GIT_REPOS\
├── mu-brighter-support-main/          ← CODE REPO
│   ├── brighter-core/                ← Legacy code
│   ├── site-essentials/              ← New modular code
│   ├── *.php                         ← Plugin files
│   └── .gitignore                    ← Excludes all docs
│
└── brighter-frameworks-docs/          ← DOCS REPO
    ├── frameworks/                   ← Framework definitions
    ├── implementation/               ← Implementation guides
    ├── technical/                    ← Technical specs
    ├── Playbooks/                    ← Operational guides
    └── GLOSSARY.md                   ← Terminology
```

---

## Single Source of Truth (SSOT)

### Documentation SSOT
- **Location**: `brighter-frameworks-docs/`
- **Update**: Update docs here only
- **Access**: All tools reference this location

### Code SSOT
- **Location**: `mu-brighter-support-main/`
- **Update**: Code changes here
- **Deploy**: This repo deploys to production (docs excluded)

---

## Migration Notes

### What Moved
- All framework documentation → `brighter-frameworks-docs/frameworks/`
- All implementation guides → `brighter-frameworks-docs/implementation/`
- All technical specs → `brighter-frameworks-docs/technical/`

### What Stayed (Temporarily)
- Development docs in `mu-brighter-support-main/` (excluded from Git, local reference only)
- These will be archived/moved as project progresses

---

## Questions?

- **Where is [document]?** → Check `brighter-frameworks-docs/`
- **Where do I update docs?** → `brighter-frameworks-docs/`
- **Where is the code?** → `mu-brighter-support-main/`
- **What gets deployed?** → Code only (docs excluded via .gitignore)

---

**End of Documentation Reference Guide**


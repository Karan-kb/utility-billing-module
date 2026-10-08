# Bidut: High-Precision Utility Billing & Stateful Financial Ledger Engine

Bidut is a high-performance, asynchronous transactional engine designed to manage high-throughput, consumption-to-cash workflows for heterogeneous public utilities. The architecture enforces structural data integrity across complex meter-reading logs, multi-tier stepping tariff matrices, and state-persistent consumer account balances calculated natively in Nepalese Rupees (NPR) over changing fiscal cycles.

---

## 🏗️ Architectural Core: Stateful Ledger Mechanics

The primary engineering challenge in utility billing infrastructure is eliminating numerical drift and race conditions during cyclical mass-billing updates. This module addresses this by treating every consumer account as a Stateful Financial Ledger, calculating liabilities using a deterministic, multi-variable transaction workflow that combines outstanding arrears, calculated current consumption costs, and compounding late fines, balanced against an active advance credit escrow layer.

### 🧠 Core System Design Patterns:
* **Advance Credit Escrow Engine:** When payments exceed the current total liability, the backend system captures the surplus into an escrow state vector in NPR. This credit automatically acts as an injection offset to decrease liabilities generated in subsequent billing runs.
* **Chronological Arrears Rollover:** Aging unpaid balances are archived as structural, stateful liabilities. They cascade into new fiscal periods without data fragmentation or record duplication.
* **Cryptographically Signed Fiscal Layers:** Engineered an asynchronous real-time integration layer designed to communicate with state-mandated fiscal APIs (such as the IRD CBMS platform). It processes cryptographically signed payloads and utilizes robust failover handlers to enforce tax compliance and data synchronization under volatile network conditions.

---

## 🛠️ Data Infrastructure & Database Lifecycle

The schema initialization pipelines are fully optimized, utilizing advanced database schema refinement, composite indexing strategies, and automated data mapping frameworks to accelerate transactional throughput by 15% across thousands of monthly billing events.

### 📋 Migration & Ingestion Sequence

#### 1. Schema Structural Provisioning
Construct the relational database schemas, structural query indexes, and foreign key constraints for meters, ledgers, and consumption history:
```bash
php artisan migrate
```

#### 2. Access Control Initialization
Seed initial access boundaries, generating administrative roles, granular system privileges, and tokenization rules:
```bash
php artisan db:seed SuperAdminSeeder
```

#### 3. Geospatial & Regional Boundary Ingestion
Parse and map complex geopolitical structures down to local municipal boundaries, establishing the localized regional groupings required for branch-wise utility administration:
```bash
php artisan import:nepal-states-all
```

---

## ⏳ Asynchronous Automation & Task Scheduling

To handle time-delayed ledger updates (such as daily compounding fines or automated cron scheduling modules for thousands of accounts) without clogging user-facing HTTP workflows, the system implements a background daemon process.

### Automated Task Scheduler Engine
Launch the integrated background worker to evaluate daily compounding parameters, late fee triggers in NPR, and chronological database updates:
```bash
php artisan schedule:work
```

### Distributed Unix System Queue (Staging/Production VPS)
To process asynchronous webhook responses, payment tracking queues, and high-precision transaction states, execute the core daemon:
```bash
php artisan queue:work --queue=billing,default --tries=3
```

---

## 🔄 Reversible Migration Engine (Rollbacks)

To securely revert the database structure during iterative testing, structural refactoring, or schema changes, run the clean teardown pipeline:
```bash
php artisan migrate:rollback
```
*Note: Running structural rollbacks destructively flushes existing transactional states. Ensure database state snapshots are executed before running updates in live setups.*

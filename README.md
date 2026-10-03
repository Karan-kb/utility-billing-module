# Bidut: Heterogeneous Utility Billing & Persistent Financial Ledger Module

This module serves as the core transactional engine for **Bidut**, an automated enterprise billing system designed to manage high-throughput, consumption-to-cash workflows for heterogeneous public utilities (**Electricity and Water networks**). The architecture handles complex meter-reading logs, multi-tier stepping tariff matrices, and state-persistent consumer account balances over changing fiscal cycles.

---

## 🏗️ Architectural Deep Dive: Stateful Ledger Mechanics

The primary engineering challenge in long-term utility billing infrastructure is eliminating numerical drift and race conditions during cyclical mass-billing updates. This module addresses this by treating every consumer account as a **Stateful Financial Ledger**, calculating liabilities using a deterministic, multi-variable transaction formula:

$$\text{Current Balance} = \text{Arrears (Historical Dues)} + \text{Calculated Consumption Cost} + \text{Compounding Fines} - \text{Advance Credit Escrow}$$

### 🧠 Core State Mechanics Implemented:
* **Advance Credit Escrow Engine:** When payments exceed the current total liability, the backend system captures the surplus into an escrow state vector. This credit automatically acts as an injection offset to decrease liabilities generated in subsequent billing runs.
* **Chronological Arrears Rollover:** Aging unpaid balances are archived as structural, stateful liabilities. They cascade into new fiscal periods without data fragmentation or record duplication.
* **Compounding Fine Multipliers:** Late fees are treated as dynamic, multi-tier parameters that scale automatically based on regional grace periods and regulatory constraints.

---

## 🛠️ Data Infrastructure & Database Lifecycle

The schema initialization pipelines are decoupled to allow rapid, idempotent setups across local environments and distributed Virtual Private Server (VPS) staging environments.

### 📋 Migration & Bootstrapping Sequence

#### 1. Schema Structural Provisioning
Construct the relational database schemas, composite indexes, and foreign key constraints for meters, ledgers, and consumption history:
```bash
php artisan migrate
```

#### 2. Access Control Initialization
Seed initial access boundaries, generating administrative roles and baseline system privileges:
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

To handle time-delayed ledger updates (such as daily compounding fines or monthly billing cycles) without clogging user-facing HTTP workflows, the system implements background processing:

### Windows-Based Automation Worker
For execution across localized server topologies or local evaluation setups, use the integrated script to fire up the system background task runner:
```bash
run-fines-scheduler.bat
```

### Distributed Unix System Queue (Staging/Production VPS)
For automated background processing on a live virtual server, launch the asynchronous queue worker engine:
```bash
php artisan queue:work
```

---

## 🔄 Reversible Migration Engine (Rollbacks)

To securely revert the database structure during iterative testing, structural refactoring, or schema changes, run the clean teardown pipeline:
```bash
php artisan migrate:rollback
```
*Note: Running structural rollbacks destructively flushes existing transactional states. Ensure database state snapshots are executed before running updates in live staging setups.*

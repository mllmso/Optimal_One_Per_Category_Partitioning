# Optimal_One_Per_Category_Partitioning

**Author**: [mllmso](https://orcid.org/0009-0005-3698-7366)

## Overview

This repository provides **benchmark instances** for the **One-Per-Category Partitioning (OPC)** problem.

All instances are:
- ✅ generated with **reproducible seeds** or by using the web service [RANDOM.ORG](https://www.random.org/)
- ✅ solved to **optimality** *(all subset sums equal or differ by at most 1—achieving the theoretical optimum)*
- ✅ computed with an **original algorithm** *(not included in the repository)*

All optimal solutions—**uniform and non-uniform**—can be **independently verified** using the provided validation script.

## Problem

**One-Per-Category Partitioning (OPC)**

Given **C disjoint categories**, each containing **M items with weights**, the goal is to partition all items into **M subsets** such that:

* each subset contains **exactly one item from every category**, and
* the **subset weight sums are as balanced as possible**.

The problem is **NP-hard**—no polynomial-time algorithm can solve all instances optimally, unless P = NP.

**OPC** has practical applications in multiprocessor scheduling with diversity or resource constraints. 

Ref.: [Grokipedia](https://grokipedia.com/page/balanced_number_partitioning#one-per-category-partitioning) • [Wikipedia](https://en.wikipedia.org/wiki/Balanced_number_partitioning#One-per-category_partitioning)

## Benchmark

**Legend**
- **C** = Number of categories
- **M** = Number of items per category

*(use `generate_opc_instance.php` for reproducibility)*

**Constraints**
- Partition all **C × M** items into **M** subsets
- Each subset contains **exactly 1 item** from every category

**Objective**
- All subset sums equal, or differ by at most 1 🟩
- Subset sum: either `floor(target)` or `ceil(target)`, where `target = sum of all items / M`

**Validate solution**  
- Use the script `validate_opc_solution.php`

**Largest instances**

|#    | Instance    | C    | M      | Range        | Types                  | Optimal  |
|-----|-------------|------|--------|--------------|------------------------|----------|
| 1–2 | `c10-m100`  | `10` | `100`  | `[1-10,000]` |`uniform / non-uniform` |  100% 🟩 |
| 3–4 | `c10-m1000` | `10` | `1000` | `[1-10,000]` |`uniform / non-uniform` |  100% 🟩 |
| 5–6 | `c20-m50`   | `20` | `50`   | `[1-10,000]` |`uniform / non-uniform` |  100% 🟩 |
| 7–8 | `c25-m25`   | `25` | `25`   | `[1-10,000]` |`uniform / non-uniform` |  100% 🟩 |

> 📜 **OPC—State of the Art (*early* 2026)**
>
> - **Exact solvers** typically struggle as **C** and **M** grow.
> - **Heuristics** (e.g., layered LPT) scale well but **do not guarantee optimality**.

## License

[![License](https://img.shields.io/badge/License-CC%20BY%204.0-blue.svg)](https://creativecommons.org/licenses/by/4.0/)

**Author**: [mllmso](https://orcid.org/0009-0005-3698-7366)

- ✅ Free to **share and adapt**  
- 🚫 **NonCommercial only**—no commercial use  
- ℹ️ **Attribution required**—give credit + link to license  

Review the [Full Legal Code](https://creativecommons.org/licenses/by/4.0/legalcode.en)

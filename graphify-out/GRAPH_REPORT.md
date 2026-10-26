# Graph Report - ORM  (2026-10-27)

## Corpus Check
- 69 files · ~89,570 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 2 file(s) not represented in the graph (top: (none) 2)

## Summary
- 1093 nodes · 2094 edges · 81 communities (22 shown, 59 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 157 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Query Builder Core
- Shared Query Utilities
- Models and Scopes
- Blueprint Column Types
- Database Facade
- Eloquent Collections
- Relationship Loading
- Blueprint Examples
- Lazy Collections
- Query Execution
- Collection Transformations
- Lifecycle Event Classes
- Model Attributes and Casts
- Column Definitions
- Lazy Collection Closures
- Model Lookup Exceptions
- WordPress Models
- Pivot Models
- Composer Package Setup
- Model Boot and Events
- Pagination and Iteration
- Lazy Collection Aggregates
- Methods Reference
- Attribute Casting Docs
- Lazy Examples and Users
- Custom Attribute Casts
- Query Debugging
- Event Dispatcher
- Readme Feature Guide
- Schema Definition Docs
- Documentation Index
- Collection Serialization
- Relationship Query Example
- Relationship Feature Docs
- Readme Relations and Loading
- WordPress Comment Models
- Blueprint Column Placement
- Blueprint Numeric Precision
- Derived Table Queries
- Conditional Query Example
- Query Scopes
- Comment and Site Metadata
- WordPress Post Models
- WordPress Core Integration
- Term Taxonomy Models
- Project Agent Guidelines
- Composite Relationship Example
- Review Relationship Example
- Mass Pruning
- Prunable Models
- WordPress Blog Models
- WordPress Site Models
- WordPress Term Models
- Term Relationship Models
- Blueprint Schema Concepts
- Product Relationship Example
- Blog Metadata
- Post Metadata
- User Metadata
- Product Model Example
- Truncate Example
- Model Event Documentation
- Agent Documentation

## God Nodes (most connected - your core abstractions)
1. `QueryBuilder` - 215 edges
2. `Model` - 192 edges
3. `Blueprint` - 128 edges
4. `Collection` - 83 edges
5. `LazyCollection` - 49 edges
6. `WordPressModel` - 21 edges
7. `DB` - 15 edges
8. `ModelEvent` - 15 edges
9. `ColumnDefinition` - 14 edges
10. `QueryLogger` - 12 edges

## Surprising Connections (you probably didn't know these)
- `User` --inherits--> `Model`  [EXTRACTED]
  Example/WhenExample.php → Model.php
- `Blueprint` --references--> `Column Modifiers`  [EXTRACTED]
  Blueprint.php → Blueprint.md
- `Blueprint` --references--> `Blueprint Column Types`  [EXTRACTED]
  Blueprint.php → Blueprint.md
- `Schema Indexing` --references--> `Blueprint`  [EXTRACTED]
  Blueprint.md → Blueprint.php
- `SchemaBuilder` --references--> `Blueprint`  [EXTRACTED]
  Blueprint.md → Blueprint.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **WPORM Query Execution Flow** — readme_model, readme_query_builder, readme_raw_table_queries, readme_bound_sql [INFERRED 0.85]
- **Relationship Loading and Aggregation Pattern** — methods_relationships, methods_eager_loading, methods_with_count, methods_relationship_aggregates [EXTRACTED 1.00]
- **WordPress Multisite Model Access Pattern** — wordpresscore_core_models, wordpresscore_wordpress_model, wordpresscore_multisite_resolution [EXTRACTED 1.00]

## Communities (81 total, 59 thin omitted)

### Community 2 - "Shared Query Utilities"
Cohesion: 0.07
Nodes (4): Helpers, {closure#12}(), {closure#13}(), {closure#14}()

### Community 3 - "Models and Scopes"
Cohesion: 0.04
Nodes (3): DynamicWhereUser, DemoProduct, Model

### Community 5 - "Database Facade"
Cohesion: 0.05
Nodes (4): DB, ExampleWithAppends, Parts, QueryLogger

### Community 7 - "Relationship Loading"
Cohesion: 0.10
Nodes (6): {closure#54}(), {closure#57}(), {closure#60}(), {closure#61}(), {closure#62}(), {closure#63}()

### Community 8 - "Blueprint Examples"
Cohesion: 0.08
Nodes (8): {closure#1}(), {closure#2}(), ExampleWithAppends, Parts, Product, Review, User, SchemaBuilder

### Community 14 - "Collection Transformations"
Cohesion: 0.12
Nodes (4): {closure#10}(), {closure#12}(), {closure#13}(), {closure#6}()

### Community 17 - "Lifecycle Event Classes"
Cohesion: 0.21
Nodes (14): Created, Creating, Deleted, Deleting, Restored, Restoring, Retrieved, Saved (+6 more)

### Community 24 - "WordPress Models"
Cohesion: 0.14
Nodes (6): Link, Option, RegistrationLog, Signup, TermMeta, WordPressModel

### Community 26 - "Composer Package Setup"
Cohesion: 0.17
Nodes (11): authors, autoload, classmap, psr-4, description, license, name, MJ\\WPORM\\ (+3 more)

### Community 30 - "Lazy Collection Aggregates"
Cohesion: 0.20
Nodes (4): {closure#11}(), {closure#12}(), {closure#15}(), {closure#16}()

### Community 31 - "Methods Reference"
Cohesion: 0.20
Nodes (10): Collection, LazyCollection Batch Processing, Mass Assignment Protection, Model, Prunable and MassPrunable, QueryBuilder, Reusable Scope Classes, Hidden and Visible Attributes (+2 more)

### Community 33 - "Attribute Casting Docs"
Cohesion: 0.25
Nodes (8): Model Attribute Casting, Built-in Cast Types, CastableInterface, Class-Based Custom Casts, DateTime Cast, JSON Cast, Unix Timestamp Cast, Attribute Casting

### Community 36 - "Query Debugging"
Cohesion: 0.43
Nodes (6): Query Bindings Inspection, Query Debug Mode, Query Logging, Generated SQL Inspection, Query Logging and Debugging, Query Logging and Debugging

### Community 38 - "Readme Feature Guide"
Cohesion: 0.25
Nodes (8): Mass Assignment Protection, Model-Based Data Access, Model Events and Observers, Query Builder, Soft Deletes, Transactions, WordPress Core Table Models, WPORM

### Community 39 - "Schema Definition Docs"
Cohesion: 0.29
Nodes (6): ColumnDefinition, Column Modifiers, SchemaBuilder, Column Change and Position Modifiers, SchemaBuilder and Blueprint Operations, Schema Management

### Community 40 - "Documentation Index"
Cohesion: 0.43
Nodes (7): Blueprint Column Types Documentation, WPORM Cast Types Documentation, DB Table Query Documentation, QueryBuilder Debugging Documentation, Model Methods Documentation, WPORM Documentation, WordPress Core Models Documentation

### Community 43 - "Relationship Feature Docs"
Cohesion: 0.29
Nodes (6): Composite-Key Relationships, Eager Loading, Relationship Aggregate Subqueries, Relationship Existence Filtering, Model Relationships, Eager-Loaded Relationship Counts

### Community 44 - "Readme Relations and Loading"
Cohesion: 0.29
Nodes (5): Collections, Eager Loading, Polymorphic Morph Map, Raw Table Queries, Eloquent-Style Relationships

### Community 49 - "Derived Table Queries"
Cohesion: 0.33
Nodes (4): Derived Tables, QueryBuilder, WordPress $wpdb, Raw Table Queries with DB::table()

### Community 54 - "WordPress Core Integration"
Cohesion: 0.40
Nodes (5): WordPress Core Models, WordPress Core Model Relationships, Multisite Table Resolution, WordPress Domain APIs, WordPressModel Base Class

### Community 56 - "Project Agent Guidelines"
Cohesion: 0.50
Nodes (3): Composer PSR-4 Autoloading, WordPress $wpdb API, WPORM

### Community 66 - "Blueprint Schema Concepts"
Cohesion: 0.67
Nodes (3): Blueprint Column Types, Foreign Key Column Types, Timestamp and Soft-Delete Columns

## Knowledge Gaps
- **35 isolated node(s):** `Repository Guidelines`, `QueryBuilder Debugging Documentation`, `WordPress Core Models Documentation`, `WordPress $wpdb API`, `Composer PSR-4 Autoloading` (+30 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 381 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **59 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Model` connect `Models and Scopes` to `Shared Query Utilities`, `Database Facade`, `Eloquent Collections`, `Relationship Loading`, `Blueprint Examples`, `Model Query API`, `Blueprint SQL Types`, `Model Persistence Events`, `Model Attributes and Casts`, `Mass Assignment and Casting`, `Model Relationships`, `Model Lookup Exceptions`, `WordPress Models`, `Pivot Models`, `Model Boot and Events`, `Pagination and Iteration`, `Relationship Query Example`, `Conditional Query Example`, `Query Scopes`, `Composite Relationship Example`, `Review Relationship Example`, `Product Relationship Example`, `Product Model Example`, `Truncate Example`?**
  _High betweenness centrality (0.477) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `Blueprint` (e.g. with `.create()` and `.table()`) actually correct?**
  _`Blueprint` has 2 INFERRED edges - model-reasoned connections that need verification._
- **What connects `Repository Guidelines`, `QueryBuilder Debugging Documentation`, `WordPress Core Models Documentation` to the rest of the system?**
  _35 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Query Builder Closures` be split into smaller, more focused modules?**
  _Cohesion score 0.030303030303030304 - nodes in this community are weakly interconnected._
- **Why does `QueryBuilder` connect `Query Builder Core` to `Query Builder Closures`, `Query Mutations`, `Shared Query Utilities`, `Models and Scopes`, `Database Facade`, `Relationship Loading`, `Lazy Collections`, `Query Execution`, `Query Constraints`, `Derived Table Queries`, `Query Scopes`, `Model Relationships`, `Model Lookup Exceptions`, `Pagination and Iteration`?**
  _High betweenness centrality (0.321) - this node is a cross-community bridge._
- **Are the 4 inferred relationships involving `Collection` (e.g. with `.collect()` and `.eagerLoadRelation()`) actually correct?**
  _`Collection` has 4 INFERRED edges - model-reasoned connections that need verification._
- **Should `Query Builder Core` be split into smaller, more focused modules?**
  _Cohesion score 0.04915254237288136 - nodes in this community are weakly interconnected._
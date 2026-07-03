# CLAUDE.md

## Stack

* XenForo 2.3
* PHP 8.x
* MySQL
* Follow XenForo coding standards and existing project conventions.

---

## Core Principles

* Prefer consistency over introducing new abstractions.
* Minimize changes to only the files required for the task.
* Preserve backward compatibility unless explicitly instructed otherwise.
* Follow existing project patterns whenever possible.
* Do not refactor unrelated code.

---

## Workflow

For every task:

1. Inspect the relevant files first.
2. Explain the implementation plan.
3. List assumptions and impacted files.
4. Verify APIs and dependencies exist.
5. Implement the requested changes.
6. Run validation checks.
7. Review your own work and summarize risks.

Do not immediately write code if requirements are unclear.

---

## Never Assume

* Never invent XenForo classes, methods, services, repositories, events, routes, or entity relations.
* Search the codebase before using an API.
* Verify classes and methods exist before calling them.
* Verify entity relations exist before using them.
* Verify database columns exist before referencing them.
* Ask for clarification instead of guessing.

---

## XenForo Conventions

### Entities and Finders

* Prefer Entities, Finders, Repositories, and Services over direct database queries.
* Use raw SQL only when there is a clear performance or technical reason.

### Services

* Reuse existing XenForo services whenever possible.
* Do not introduce new services unless complexity justifies them.

### Controllers

* Keep controllers thin.
* Place business logic inside services, repositories, or entities when appropriate.

### Database Changes

For schema changes:

1. Create migration steps in Setup.php.
2. Apply the upgrade.
3. Update entities and dependent code.
4. Verify inserts, updates, and queries.

Never reference schema changes before migrations exist.

---

## Validation Checklist

Before finishing:

* Verify PHP syntax.
* Verify namespaces and imports.
* Verify service names.
* Verify repository names.
* Verify entity relations.
* Verify route names.
* Verify template names.
* Verify database columns.
* Remove unused imports and dead code.
* Check for backward compatibility issues.

---

## Testing

Consider:

* Empty values
* Null values
* Invalid IDs
* Missing records
* Permission failures
* Duplicate data
* Large datasets
* Repeated execution
* Error handling
* Edge cases

---

## Reviewer Mode

After implementation:

Review the code as a senior XenForo developer.

Check for:

* Syntax issues
* Logic bugs
* Incorrect XenForo APIs
* Missing permissions
* N+1 queries
* Race conditions
* Error handling issues
* Security issues
* Upgrade issues
* Edge cases

Do not assume the implementation is correct.

---

## XenForo Reference

Official docs: https://docs.xenforo.com/devs

---

### Add-on Structure

- Addon ID format: `Vendor/Name` → files at `src/addons/Vendor/Name/`
- `addon.json` — required: `title`, `version_string`, `version_id`, `dev`
- `version_id` uses 8-digit format `aabbccde` (aa=major, bb=minor, cc=patch, d=state, e=state version). Example: `1.0.5 stable` = `1000570`
- `_output/` — dev-only, excluded from releases
- `_data/` — XML master data (options, phrases, templates, routes, etc.)
- `build.json` — controls what gets excluded from the release zip

---

### Setup.php (Install / Upgrade / Uninstall)

- Extend `\XF\AddOn\AbstractSetup`
- Use traits: `StepRunnerInstallTrait`, `StepRunnerUpgradeTrait`, `StepRunnerUninstallTrait`
- Install steps: `installStep1()`, `installStep2()`, ...
- Upgrade steps: `upgrade{VERSION_ID}Step1()` — VERSION_ID is the full numeric `version_id` from addon.json (e.g. `upgrade1000570Step1`)
- Uninstall steps: `uninstallStep1()`, `uninstallStep2()`, ...

**Critical rule:** All DB columns added in upgrade steps must also exist in `installStep1()`. Upgrade steps only run for existing installs — fresh installs only run `installStepN()` methods. Always consolidate before release.

---

### Entities

- Define structure via `getStructure(Structure $structure)`
- `$structure->table`, `$structure->shortName`, `$structure->primaryKey`
- `$structure->columns` — type constants: `UINT`, `STR`, `BOOL`, `FLOAT`, `INT`
- Lifecycle hooks: `_preSave()`, `_postSave()`, `_preDelete()`, `_postDelete()`
- Use `isChanged('column')` to detect modifications

---

### Finders

- `\XF::finder('Vendor\Addon:EntityName')`
- Methods: `->where()`, `->with()`, `->order()`, `->limit()`, `->limitByPage()`, `->fetchOne()`, `->fetch()`
- Where operators: `=`, `<>`, `>`, `>=`, `<`, `<=`, `LIKE`, `BETWEEN`
- Use `->whereOr()` for OR conditions

---

### Controllers

- Action methods named `actionFoo()` — maps from URL segment `foo`
- No-segment URL calls `actionIndex()`
- Reply types: `$this->view()`, `$this->redirect()`, `$this->error()`, `$this->message()`, `$this->exception()`, `$this->reroute()`
- `$this->view('Vendor\Addon:Name', 'template_name', $viewParams)`
- When extending actions, check reply type: `if ($reply instanceof \XF\Mvc\Reply\View)`

---

### Routing

- Routes defined in Admin CP > Development > Routes (Public / Admin)
- Route prefix maps to controller short name e.g. `XF:Account` → `src/XF/Pub/Controller/Account.php`
- Route format extracts URL params: `:int<param_name>`
- Params arrive as `ParameterBag` in action methods: `$params->param_name`
- Build links: `$this->app->router('public')->buildLink('route-prefix', $entity)`

---

### Short Class Names

- `XF:User` → `XF\Entity\User`
- `CoderBeams\Telegram:TelegramPost` → `CoderBeams\Telegram\Entity\TelegramPost`
- Same pattern applies to Finders, Repositories, Services

---

## Project-Specific Rules

Project-specific requirements may exist in the docs directory.

Before implementing project-specific functionality, inspect and follow any relevant files in:

docs/

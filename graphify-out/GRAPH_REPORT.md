# Graph Report - .  (2026-05-29)

## Corpus Check
- 189 files · ~84,026 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 875 nodes · 1241 edges · 80 communities (38 shown, 42 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 30 edges (avg confidence: 0.78)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_E2E Test Infrastructure|E2E Test Infrastructure]]
- [[_COMMUNITY_Application Core|Application Core]]
- [[_COMMUNITY_Ability Registry|Ability Registry]]
- [[_COMMUNITY_Hook Installer|Hook Installer]]
- [[_COMMUNITY_Service Edge Cases Tests|Service Edge Cases Tests]]
- [[_COMMUNITY_Installer Tests|Installer Tests]]
- [[_COMMUNITY_Config & Project Init|Config & Project Init]]
- [[_COMMUNITY_Console Flow Tests|Console Flow Tests]]
- [[_COMMUNITY_Semantic Concepts|Semantic Concepts]]
- [[_COMMUNITY_Composer Metadata|Composer Metadata]]
- [[_COMMUNITY_Diff Service Tests|Diff Service Tests]]
- [[_COMMUNITY_Installer Service|Installer Service]]
- [[_COMMUNITY_Check Service Tests|Check Service Tests]]
- [[_COMMUNITY_Test Support Traits|Test Support Traits]]
- [[_COMMUNITY_Graphify Detection|Graphify Detection]]
- [[_COMMUNITY_Check Service|Check Service]]
- [[_COMMUNITY_Test Dependencies|Test Dependencies]]
- [[_COMMUNITY_Hook Checker Tests|Hook Checker Tests]]
- [[_COMMUNITY_Preset Registry|Preset Registry]]
- [[_COMMUNITY_Command Edge Cases|Command Edge Cases]]
- [[_COMMUNITY_Module 20|Module 20]]
- [[_COMMUNITY_Module 21|Module 21]]
- [[_COMMUNITY_Module 22|Module 22]]
- [[_COMMUNITY_Module 23|Module 23]]
- [[_COMMUNITY_Module 24|Module 24]]
- [[_COMMUNITY_Module 25|Module 25]]
- [[_COMMUNITY_Module 26|Module 26]]
- [[_COMMUNITY_Module 27|Module 27]]
- [[_COMMUNITY_Module 28|Module 28]]
- [[_COMMUNITY_Module 29|Module 29]]
- [[_COMMUNITY_Module 30|Module 30]]
- [[_COMMUNITY_Module 31|Module 31]]
- [[_COMMUNITY_Module 32|Module 32]]
- [[_COMMUNITY_Module 33|Module 33]]
- [[_COMMUNITY_Module 34|Module 34]]
- [[_COMMUNITY_Module 35|Module 35]]
- [[_COMMUNITY_Module 36|Module 36]]
- [[_COMMUNITY_Module 37|Module 37]]
- [[_COMMUNITY_Module 38|Module 38]]
- [[_COMMUNITY_Module 39|Module 39]]
- [[_COMMUNITY_Module 40|Module 40]]
- [[_COMMUNITY_Module 41|Module 41]]
- [[_COMMUNITY_Module 42|Module 42]]
- [[_COMMUNITY_Module 43|Module 43]]
- [[_COMMUNITY_Module 44|Module 44]]
- [[_COMMUNITY_Module 45|Module 45]]
- [[_COMMUNITY_Module 46|Module 46]]
- [[_COMMUNITY_Module 47|Module 47]]
- [[_COMMUNITY_Module 48|Module 48]]
- [[_COMMUNITY_Module 49|Module 49]]
- [[_COMMUNITY_Module 50|Module 50]]
- [[_COMMUNITY_Module 51|Module 51]]
- [[_COMMUNITY_Module 52|Module 52]]
- [[_COMMUNITY_Module 53|Module 53]]
- [[_COMMUNITY_Module 54|Module 54]]
- [[_COMMUNITY_Module 55|Module 55]]
- [[_COMMUNITY_Module 56|Module 56]]
- [[_COMMUNITY_Module 57|Module 57]]
- [[_COMMUNITY_Module 58|Module 58]]
- [[_COMMUNITY_Module 59|Module 59]]
- [[_COMMUNITY_Module 60|Module 60]]
- [[_COMMUNITY_Module 61|Module 61]]
- [[_COMMUNITY_Module 62|Module 62]]
- [[_COMMUNITY_Module 63|Module 63]]
- [[_COMMUNITY_Module 64|Module 64]]
- [[_COMMUNITY_Module 65|Module 65]]
- [[_COMMUNITY_Module 66|Module 66]]
- [[_COMMUNITY_Module 67|Module 67]]
- [[_COMMUNITY_Module 68|Module 68]]
- [[_COMMUNITY_Module 69|Module 69]]
- [[_COMMUNITY_Module 70|Module 70]]
- [[_COMMUNITY_Module 73|Module 73]]
- [[_COMMUNITY_Module 74|Module 74]]
- [[_COMMUNITY_Module 75|Module 75]]
- [[_COMMUNITY_Module 76|Module 76]]
- [[_COMMUNITY_Module 77|Module 77]]
- [[_COMMUNITY_Module 79|Module 79]]

## God Nodes (most connected - your core abstractions)
1. `ServiceEdgeCasesTest` - 40 edges
2. `InstallerTest` - 28 edges
3. `ConsoleFlowsTest` - 27 edges
4. `AbilityRegistry` - 23 edges
5. `CheckServiceTest` - 23 edges
6. `AbilityDiffServiceExtendedTest` - 23 edges
7. `CheckService` - 23 edges
8. `HookInstallerTest` - 21 edges
9. `Installer` - 20 edges
10. `EndToEndTestCase` - 19 edges

## Surprising Connections (you probably didn't know these)
- `APM CLI Tool` --implements--> `Symfony Console Framework`  [EXTRACTED]
  README.md → composer.json
- `Directory Mirroring Strategy` --implements--> `Multi-Platform Targeting (Cursor/Kiro)`  [INFERRED]
  src/Service/DirectoryMirrorService.php → abilities.yaml
- `Hook System` --implements--> `Multi-Platform Targeting (Cursor/Kiro)`  [INFERRED]
  src/Service/HookInstaller.php → abilities.yaml
- `Install Workflow` --references--> `Preset System`  [EXTRACTED]
  src/Command/InstallCommand.php → abilities.yaml
- `Install Workflow` --references--> `Abilities Registry Concept`  [EXTRACTED]
  src/Command/InstallCommand.php → abilities.yaml

## Hyperedges (group relationships)
- **Ability Lifecycle (Install/Check/Uninstall)** — install_workflow, check_workflow, uninstall_workflow, abilities_registry_concept [EXTRACTED 0.90]
- **Platform Targeting System** — multi_platform_targeting, directory_mirroring, abilities_yaml_config, hook_system [INFERRED 0.80]
- **Agent Skill Ecosystem** — gitflow_workflow, graphify_knowledge_graph, spec_planning_workflow, spec_execution_workflow, code_review_agent, spec_gatekeeper_agent [EXTRACTED 0.90]

## Communities (80 total, 42 thin omitted)

### Community 0 - "E2E Test Infrastructure"
Cohesion: 0.04
Nodes (4): EndToEndTestCase, PresetLifecycleTest, ShowAndUpdateTest, TypedCommandsLifecycleTest

### Community 1 - "Application Core"
Cohesion: 0.06
Nodes (14): Application, ConsoleRegistration, CommandErrorPathsTest, Installer, KnowledgeBaseUpdater, OutputInterface, PresetRegistry, SymfonyApplication (+6 more)

### Community 2 - "Ability Registry"
Cohesion: 0.06
Nodes (8): RuntimeException, AbilityRegistry, AbilityRegistryException, DirectoryMirrorService, GitIgnoreTemplateService, self, DirectoryMirrorServiceTest, ProjectInitializerTest

### Community 3 - "Hook Installer"
Cohesion: 0.07
Nodes (4): HookInstaller, HookRegistryException, self, HookInstallerTest

### Community 6 - "Config & Project Init"
Cohesion: 0.11
Nodes (4): PackagePaths, ProjectInitializer, ProjectInitializerExtendedTest, self

### Community 8 - "Semantic Concepts"
Cohesion: 0.11
Nodes (23): Abilities Registry Concept, Abilities Relocation Spec, abilities.yaml Configuration, APM CLI Tool, Check Write Length Hook, Code Review Agent, Directory Mirroring Strategy, E2E Testing Framework (+15 more)

### Community 9 - "Composer Metadata"
Cohesion: 0.09
Nodes (22): autoload, autoload-dev, psr-4, psr-4, bin, description, keywords, license (+14 more)

### Community 13 - "Test Support Traits"
Cohesion: 0.24
Nodes (3): AbilityRegistry, RemovesDirTrait, TestCase

### Community 14 - "Graphify Detection"
Cohesion: 0.14
Nodes (13): files, code, document, image, paper, video, graphifyignore_patterns, needs_graph (+5 more)

### Community 16 - "Test Dependencies"
Cohesion: 0.22
Nodes (5): CheckService, RestoresCwdTrait, RestoresEnvTrait, ConsoleRegistrationTest, TypedCheckCommandsTest

### Community 21 - "Module 21"
Cohesion: 0.29
Nodes (4): ShowCommand, InputInterface, OutputInterface, SymfonyStyle

### Community 26 - "Module 26"
Cohesion: 0.36
Nodes (4): InstallCommand, InputInterface, OutputInterface, SymfonyStyle

### Community 33 - "Module 33"
Cohesion: 0.39
Nodes (4): PresetAddAbilityCommand, InputInterface, OutputInterface, PresetRegistry

### Community 34 - "Module 34"
Cohesion: 0.39
Nodes (4): PresetCreateCommand, InputInterface, OutputInterface, PresetRegistry

### Community 35 - "Module 35"
Cohesion: 0.39
Nodes (4): Command, AgentCheckCommand, InputInterface, OutputInterface

### Community 36 - "Module 36"
Cohesion: 0.39
Nodes (4): PresetDeleteCommand, InputInterface, OutputInterface, PresetRegistry

### Community 37 - "Module 37"
Cohesion: 0.39
Nodes (4): PresetRemoveAbilityCommand, InputInterface, OutputInterface, PresetRegistry

### Community 41 - "Module 41"
Cohesion: 0.38
Nodes (3): CheckCommand, InputInterface, OutputInterface

### Community 42 - "Module 42"
Cohesion: 0.38
Nodes (3): SkillInstallCommand, InputInterface, OutputInterface

### Community 43 - "Module 43"
Cohesion: 0.38
Nodes (3): AgentInstallCommand, InputInterface, OutputInterface

### Community 44 - "Module 44"
Cohesion: 0.38
Nodes (3): AgentUninstallCommand, InputInterface, OutputInterface

### Community 45 - "Module 45"
Cohesion: 0.38
Nodes (3): PresetUninstallCommand, InputInterface, OutputInterface

### Community 46 - "Module 46"
Cohesion: 0.38
Nodes (3): RuleCheckCommand, InputInterface, OutputInterface

### Community 47 - "Module 47"
Cohesion: 0.38
Nodes (3): RuleInstallCommand, InputInterface, OutputInterface

### Community 48 - "Module 48"
Cohesion: 0.38
Nodes (3): RuleUninstallCommand, InputInterface, OutputInterface

### Community 49 - "Module 49"
Cohesion: 0.38
Nodes (3): SkillCheckCommand, InputInterface, OutputInterface

### Community 50 - "Module 50"
Cohesion: 0.38
Nodes (3): SkillUninstallCommand, InputInterface, OutputInterface

### Community 51 - "Module 51"
Cohesion: 0.38
Nodes (3): UpdateCommand, InputInterface, OutputInterface

### Community 57 - "Module 57"
Cohesion: 0.60
Nodes (5): extract_write_text(), log_event(), main(), int, str

### Community 59 - "Module 59"
Cohesion: 0.47
Nodes (4): APM_BASELINE_ROOT, fail(), pass(), test-task-7c.sh script

### Community 63 - "Module 63"
Cohesion: 0.67
Nodes (4): Ability Diff Detection, Check/Drift Detection Workflow, Composer Baseline Resolution, Uninstall Workflow

### Community 64 - "Module 64"
Cohesion: 0.50
Nodes (3): hooks, preToolUse, version

### Community 65 - "Module 65"
Cohesion: 0.50
Nodes (4): docs/manual/ Layer, docs/state/ Layer, Single Source of Truth (SSOT) Principle, Writing Conventions

### Community 66 - "Module 66"
Cohesion: 0.83
Nodes (3): assert_fail(), assert_pass(), test-task-7b.sh script

### Community 67 - "Module 67"
Cohesion: 0.83
Nodes (3): fail(), pass(), test-task-7d.sh script

## Knowledge Gaps
- **40 isolated node(s):** `code`, `document`, `paper`, `image`, `video` (+35 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **42 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `EndToEndTestCase` connect `E2E Test Infrastructure` to `Test Support Traits`?**
  _High betweenness centrality (0.085) - this node is a cross-community bridge._
- **Why does `ProjectInitializer` connect `Config & Project Init` to `Module 26`, `Ability Registry`?**
  _High betweenness centrality (0.064) - this node is a cross-community bridge._
- **Why does `ServiceEdgeCasesTest` connect `Service Edge Cases Tests` to `Test Dependencies`, `Test Support Traits`?**
  _High betweenness centrality (0.063) - this node is a cross-community bridge._
- **What connects `code`, `document`, `paper` to the rest of the system?**
  _50 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `E2E Test Infrastructure` be split into smaller, more focused modules?**
  _Cohesion score 0.04081632653061224 - nodes in this community are weakly interconnected._
- **Should `Application Core` be split into smaller, more focused modules?**
  _Cohesion score 0.06025641025641026 - nodes in this community are weakly interconnected._
- **Should `Ability Registry` be split into smaller, more focused modules?**
  _Cohesion score 0.059379217273954114 - nodes in this community are weakly interconnected._
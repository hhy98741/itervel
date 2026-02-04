# Parallel Development Guide (Advanced)

This guide explains how to leverage the parallel development opportunities identified in your Development Plan to build features faster using multiple AI agents simultaneously.

---

## 🎯 Understanding Parallel Development

### **What Opus Will Identify**

When you get your Development Plan from Opus, it will show dependency graphs like this:

```markdown
### Week 3-4: Core Features

**Parallel Track A: Backend APIs**

- Feature 2.1: Video Input API (depends on 1.2 Auth)
- Feature 2.2: URL Processing Service (depends on 2.1)

**Parallel Track B: Frontend UI**

- Feature 2.3: Video Creation Form (depends on 2.1 API)
- Feature 2.4: Project Dashboard (depends on 1.2 Auth, 1.3 Projects)

**Can run in parallel:** Track A and Track B (after 2.1 is done for 2.3)
```

This means:

- ✅ You CAN build 2.1 and 2.4 at the same time (no dependencies between them)
- ✅ You CAN build 2.2 and 2.3 at the same time (after 2.1 exists)
- ❌ You CANNOT build 2.3 until 2.1 exists (2.3 needs the API)

---

## 🤖 Three Approaches to Parallel Development

### **Approach 1: Single Agent Sequential (Default)**

**What it is:**

- One Claude Code instance builds everything
- Follows the plan in order: 2.1 → 2.2 → 2.3 → 2.4
- "Parallel" tracks are built sequentially

**Pros:**

- Simple to manage
- No coordination needed
- One progress file to track

**Cons:**

- Slower (features built one at a time)
- Doesn't leverage parallelization

**Timeline:**

- Features 2.1-2.4: ~4 hours sequential

**When to use:** For MVP, when you're the only person working, or when features are quick

---

### **Approach 2: Two Sessions Parallel (Moderate)**

**What it is:**

- Open TWO Claude Code instances simultaneously
- Session A builds Track A (backend)
- Session B builds Track B (frontend)
- You manually coordinate integration

**How it works:**

**Session A (Backend Track):**

```
You: Build Features 2.1 → 2.2 (Backend APIs)
Claude A: [Builds 2.1... ~30 min]
Claude A: [Builds 2.2... ~25 min]
Claude A: [Commits and done]
```

**Session B (Frontend Track) - RUNNING SIMULTANEOUSLY:**

```
You: Build Feature 2.4 first (doesn't need 2.1)
Claude B: [Builds 2.4... ~20 min]
Claude B: [Waits for 2.1 to be ready]

[After Claude A finishes 2.1, you tell Claude B:]
You: Feature 2.1 API is ready, now build 2.3
Claude B: [Builds 2.3... ~25 min]
```

**Result:**

- Session A: Took ~55 minutes (2.1 + 2.2)
- Session B: Took ~45 minutes (2.4 + 2.3, but waited for 2.1)
- **Total wall-clock time: ~55 minutes** (vs. ~100 minutes sequential)

**Pros:**

- Faster (nearly 2x speedup)
- Both agents working simultaneously
- Simple coordination (just tell B when A finishes blocking feature)

**Cons:**

- Need to manage two windows
- Manual coordination required
- Two progress files to merge
- Possible git conflicts

**Timeline:**

- Features 2.1-2.4: ~1 hour (vs ~2 hours sequential)

**When to use:** When you have 4+ hours of sequential work that can be split

---

### **Approach 3: True Multi-Agent Orchestration (Advanced)**

**What it is:**

- Use an orchestrator agent that delegates to worker agents
- Orchestrator reads dependency graph
- Spawns workers for parallel tracks
- Manages integration and merging

**Tools needed:**

- AutoGPT, CrewAI, or custom orchestration script
- Multiple API keys (or high rate limits)

**How it works:**

```python
# Pseudo-code for orchestrator

orchestrator = Orchestrator(development_plan)

# Orchestrator analyzes dependencies
parallel_batches = orchestrator.identify_parallel_batches()

# Batch 1: Features with no dependencies
batch1 = [Feature(1.1), Feature(1.3), Feature(1.4)]
workers = [spawn_worker(feature) for feature in batch1]
await all_workers_complete(workers)

# Batch 2: Features that depend on Batch 1
batch2 = [Feature(1.2), Feature(2.1), Feature(2.4)]
workers = [spawn_worker(feature) for feature in batch2]
await all_workers_complete(workers)

# Integration
orchestrator.run_integration_tests()
orchestrator.merge_progress_files()
```

**Pros:**

- Maximum parallelization
- Fully automated coordination
- Optimal build order
- Fastest possible development

**Cons:**

- Complex to set up
- Need orchestration framework
- Expensive (multiple agents running)
- Harder to debug when things go wrong

**Timeline:**

- All 20 features: Could finish in ~6-8 hours (vs ~12-15 hours sequential)

**When to use:** Large teams, production systems, when speed is critical

---

## 🎛️ Recommended Approach for Your Project

### **Use Approach 1 (Single Sequential Agent) Because:**

1. **MVP size is manageable** - 20 features over 12 weeks is very doable sequentially
2. **You're solo developer** - coordination overhead isn't worth it for one person
3. **Learning curve** - this is your first AI-built project, keep it simple
4. **Cost effective** - one agent is cheaper than multiple
5. **Progress tracking** - one DEVELOPMENT_PROGRESS.md file is easier to manage

### **When Parallelization Becomes Worth It:**

- Building Phase 2 features (after MVP validates)
- You hire another developer (they can run Track B while you/AI does Track A)
- Features take >1 hour each (more time savings from parallelization)
- You're comfortable with the workflow and want to optimize

---

## 📝 How to Use Parallel Info from Development Plan

Even with sequential development, the parallel track information is valuable:

### **Benefit 1: Optimal Build Order**

If Plan says:

```
Track A: 2.1 → 2.2
Track B: 2.3 → 2.4
```

You know: **Build Track A first** (2.1 → 2.2), then Track B (2.3 → 2.4)

Why? Track B might depend on Track A, so complete dependencies first.

### **Benefit 2: Batch Testing**

Test all Track A features together:

```bash
# After building 2.1 and 2.2
php artisan test --filter=VideoInput
php artisan test --filter=URLProcessing
```

Then test all Track B:

```bash
npm test -- VideoCreationForm
npm test -- ProjectDashboard
```

### **Benefit 3: Future Parallelization**

When you're ready to parallelize:

1. Look at Development Plan
2. See which features are marked parallel
3. Split them across sessions/agents
4. Easy!

### **Benefit 4: Understanding Architecture**

Parallel tracks often indicate architectural boundaries:

- Track A = Backend services
- Track B = Frontend components
- Track C = Infrastructure/DevOps

Helps you understand how the system is structured.

---

## 🔀 Practical Example: Week 3-4

**Development Plan says:**

```
Week 3-4: Core Video Creation

Sequential:
- 2.1: Video Input API (depends on 1.2 Auth)

Parallel Track A:
- 2.2: URL Processing Service (depends on 2.1)

Parallel Track B:
- 2.3: Video Creation Form UI (depends on 2.1)

Independent:
- 2.4: Project Dashboard UI (depends on 1.2, 1.3 only)
```

### **Approach 1: Single Agent Sequential**

**Your session:**

```
Claude: Building 2.1 (Video Input API)... 30 min
Claude: Building 2.4 (Project Dashboard)... 20 min ← do this while momentum going
Claude: Building 2.2 (URL Processing)... 25 min
Claude: Building 2.3 (Video Form)... 25 min
Total: ~100 minutes
```

**Why this order?**

- 2.1 first (blocks 2.2 and 2.3)
- 2.4 second (independent, can do anytime)
- 2.2 and 2.3 last (depend on 2.1)

### **Approach 2: Two Agents Parallel**

**Session A (Backend):**

```
10:00 AM: Start building 2.1
10:30 AM: 2.1 complete, start 2.2
10:55 AM: 2.2 complete
```

**Session B (Frontend) - Simultaneously:**

```
10:00 AM: Start building 2.4 (doesn't need 2.1)
10:20 AM: 2.4 complete
10:20 AM: Wait for 2.1...
10:30 AM: 2.1 ready! Start building 2.3
10:55 AM: 2.3 complete
```

**Total wall-clock time: 55 minutes** (vs 100 minutes)

### **Approach 3: Orchestrated**

```
10:00 AM: Orchestrator spawns workers
  - Worker A: Build 2.1
  - Worker B: Build 2.4 (parallel, no dependency)

10:20 AM: 2.4 done, Worker B idle
10:30 AM: 2.1 done
  - Worker A: Build 2.2
  - Worker B: Build 2.3

10:55 AM: All complete, orchestrator merges
```

**Total: 55 minutes, fully automated**

---

## ⚙️ Setup for Two-Agent Parallel (If You Want To Try)

### **Terminal Setup:**

**Terminal 1: Backend Track**

```bash
cd /path/to/project
git checkout -b backend-track-2
claude-code

# Paste initial/continuation prompt
# Tell it: "Build Features 2.1 and 2.2 only"
```

**Terminal 2: Frontend Track**

```bash
cd /path/to/project
git checkout -b frontend-track-2
claude-code

# Paste initial/continuation prompt
# Tell it: "Build Features 2.4 first, then wait for my signal to build 2.3"
```

### **Coordination:**

1. Both agents start working
2. Agent B finishes 2.4 first
3. You tell Agent B: "Feature 2.1 API is ready at `POST /api/videos`. Build Feature 2.3 now."
4. Both finish around same time
5. **Manual merge:**
    ```bash
    git checkout main
    git merge backend-track-2
    git merge frontend-track-2
    # Resolve any conflicts (usually none if working on different files)
    ```

### **Progress Tracking:**

Each agent creates its own `DEVELOPMENT_PROGRESS.md`:

- `DEVELOPMENT_PROGRESS_BACKEND.md`
- `DEVELOPMENT_PROGRESS_FRONTEND.md`

After merging, manually combine them into one:

```bash
cat DEVELOPMENT_PROGRESS_BACKEND.md DEVELOPMENT_PROGRESS_FRONTEND.md > DEVELOPMENT_PROGRESS.md
# Edit to merge cleanly
```

---

## ✅ Decision Matrix

| Scenario                    | Recommended Approach     | Why                                 |
| --------------------------- | ------------------------ | ----------------------------------- |
| **MVP (first build)**       | Approach 1: Sequential   | Simple, proven, manageable          |
| **4+ hour sequential work** | Approach 2: Two Agents   | 2x speedup worth coordination       |
| **Team of 2+**              | Approach 2: Two Agents   | Natural division of labor           |
| **Production system**       | Approach 3: Orchestrated | Maximum efficiency                  |
| **Learning AI dev**         | Approach 1: Sequential   | Focus on workflow, not coordination |

---

## 🎯 Bottom Line

**For your faceless video platform MVP:**

✅ **Use Approach 1** (Single Sequential Agent)

- Opus will identify parallel tracks in the plan
- You'll build them sequentially in optimal order
- Simple, predictable, works great

**Later (Phase 2 or scaling):**

Consider Approach 2 if:

- You have >20 features to build
- Features take >1 hour each
- You want to experiment with parallelization
- You're comfortable with git and coordination

**The parallel track information from Opus is still valuable even if you build sequentially** - it shows you optimal build order and architectural boundaries.

---

**Ready to start?** Use the sequential approach with the autonomous prompts I created. The Development Plan from Opus will guide you on the best order to build features, even without true parallelization.

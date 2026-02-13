# Feature: Outline Editing

**Epic**: E011-outline-and-script-generation.md
**Feature**: E011-F002
**Epic depends on**: E009-ai-configuration-and-title-generation.md
**Feature depends on**: E011-F001

## Task Description

Lets users edit the AI-generated outline before proceeding to script writing. The user can edit section titles and content inline, reorder sections via drag-and-drop, add or remove sections, and adjust timing. They can approve the outline or request it be regenerated.

**What it does**: Lets users edit the AI-generated outline before proceeding to script writing.

**Expected outcome**: The user can edit section titles and content inline, reorder sections via drag-and-drop, add or remove sections, and adjust timing. They can approve the outline or request it be regenerated.

This feature enhances the outline page (created in E011-F001) with interactive editing capabilities. Instead of a read-only display, the outline becomes an editable interface where users can customize the AI-generated structure before committing to script writing. The edited outline is saved back to the `generated_outline` JSON column and the video status transitions to `outline_approved` when the user approves.

Key capabilities:

1. **Inline editing**: Click on section titles, key points, or engagement notes to edit them directly
2. **Drag-and-drop reorder**: Reorder the main sections (not hook or closing, which stay in position)
3. **Add/Remove sections**: Add new sections between existing ones or remove sections
4. **Duration adjustment**: Modify estimated duration per section
5. **Approve/Regenerate**: Approve the edited outline or regenerate from scratch

## Objective

Enhance the outline page with inline editing for all section fields, drag-and-drop reordering of main sections, add/remove section functionality, duration adjustment inputs, and an approve action that saves the edited outline and transitions the video to `outline_approved` status. Install a drag-and-drop library, create an editable outline component, and add a backend endpoint for saving the approved outline.

## Solution Approach

### Drag-and-Drop Library

Install `@dnd-kit/core` and `@dnd-kit/sortable` for accessible, performant drag-and-drop:

```bash
npm install @dnd-kit/core @dnd-kit/sortable @dnd-kit/utilities
```

These are the recommended React drag-and-drop libraries for modern React 19 applications with excellent accessibility support.

### Editable Outline Component

Transform the outline page from a read-only display into an interactive editor. Create an `OutlineEditor` component that manages the editable state:

```tsx
function OutlineEditor({ outline, videoId }: OutlineEditorProps) {
    const [sections, setSections] = useState(outline.sections);
    const [hook, setHook] = useState(outline.hook);
    const [closing, setClosing] = useState(outline.closing);

    // DnD kit setup for reordering sections
    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    function handleDragEnd(event: DragEndEvent) {
        const { active, over } = event;
        if (active.id !== over?.id) {
            setSections((items) => {
                const oldIndex = items.findIndex((s) => s.id === active.id);
                const newIndex = items.findIndex((s) => s.id === over?.id);
                return arrayMove(items, oldIndex, newIndex);
            });
        }
    }

    // ... submit handler saves to backend
}
```

### Editable Section Card

Create an `EditableSectionCard` component that allows inline editing of section fields:

```tsx
function EditableSectionCard({
    section,
    onUpdate,
    onRemove,
    isDraggable,
}: EditableSectionCardProps) {
    const [isEditing, setIsEditing] = useState(false);

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                {isDraggable && (
                    <GripVertical className="cursor-grab text-muted-foreground" />
                )}
                <InlineEdit
                    value={section.title}
                    onSave={(title) => onUpdate({ ...section, title })}
                    className="text-lg font-semibold"
                />
                <div className="flex items-center gap-2">
                    <DurationInput
                        value={section.duration_seconds}
                        onChange={(duration) =>
                            onUpdate({ ...section, duration_seconds: duration })
                        }
                    />
                    {onRemove && (
                        <Button variant="ghost" size="icon" onClick={onRemove}>
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                <EditableKeyPoints
                    points={section.key_points}
                    onUpdate={(points) =>
                        onUpdate({ ...section, key_points: points })
                    }
                />
                <InlineEdit
                    value={section.engagement_notes}
                    onSave={(notes) =>
                        onUpdate({ ...section, engagement_notes: notes })
                    }
                    className="mt-2 text-sm text-muted-foreground italic"
                />
            </CardContent>
        </Card>
    );
}
```

### InlineEdit Component

A reusable inline text editor that toggles between display and edit mode:

```tsx
function InlineEdit({ value, onSave, className }: InlineEditProps) {
    const [editing, setEditing] = useState(false);
    const [text, setText] = useState(value);

    const handleSave = () => {
        setEditing(false);
        if (text !== value) onSave(text);
    };

    if (editing) {
        return (
            <Input
                value={text}
                onChange={(e) => setText(e.target.value)}
                onBlur={handleSave}
                onKeyDown={(e) => e.key === 'Enter' && handleSave()}
                autoFocus
                className={className}
            />
        );
    }

    return (
        <span
            onClick={() => setEditing(true)}
            className={cn(
                '-mx-1 cursor-pointer rounded px-1 hover:bg-muted/50',
                className,
            )}
        >
            {value}
        </span>
    );
}
```

### EditableKeyPoints Component

Manage the bullet-point key points list with add/remove/edit:

```tsx
function EditableKeyPoints({ points, onUpdate }: EditableKeyPointsProps) {
    const addPoint = () => onUpdate([...points, 'New key point']);
    const removePoint = (index: number) =>
        onUpdate(points.filter((_, i) => i !== index));
    const updatePoint = (index: number, value: string) => {
        const updated = [...points];
        updated[index] = value;
        onUpdate(updated);
    };

    return (
        <ul className="space-y-1">
            {points.map((point, index) => (
                <li key={index} className="flex items-center gap-2">
                    <span className="text-muted-foreground">•</span>
                    <InlineEdit
                        value={point}
                        onSave={(v) => updatePoint(index, v)}
                        className="flex-1 text-sm"
                    />
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => removePoint(index)}
                    >
                        <X className="h-3 w-3" />
                    </Button>
                </li>
            ))}
            <li>
                <Button variant="ghost" size="sm" onClick={addPoint}>
                    <Plus className="mr-1 h-3 w-3" /> Add point
                </Button>
            </li>
        </ul>
    );
}
```

### DurationInput Component

A small numeric input for section duration:

```tsx
function DurationInput({ value, onChange }: DurationInputProps) {
    const minutes = Math.floor(value / 60);
    const seconds = value % 60;

    return (
        <div className="flex items-center gap-1 text-sm text-muted-foreground">
            <Input
                type="number"
                value={minutes}
                onChange={(e) =>
                    onChange(Number(e.target.value) * 60 + seconds)
                }
                className="h-7 w-12 text-center text-xs"
                min={0}
                max={30}
            />
            <span>m</span>
            <Input
                type="number"
                value={seconds}
                onChange={(e) =>
                    onChange(minutes * 60 + Number(e.target.value))
                }
                className="h-7 w-12 text-center text-xs"
                min={0}
                max={59}
            />
            <span>s</span>
        </div>
    );
}
```

### Add Section Button

Insert a new section between existing sections:

```tsx
function AddSectionButton({ onAdd }: { onAdd: () => void }) {
    return (
        <div className="flex justify-center py-2">
            <Button variant="outline" size="sm" onClick={onAdd}>
                <Plus className="mr-1 h-4 w-4" /> Add Section
            </Button>
        </div>
    );
}
```

### Backend: Approve Endpoint

Add an approve endpoint to the OutlineController that saves the edited outline:

```php
public function approve(ApproveOutlineRequest $request, Video $video): RedirectResponse
{
    abort_unless($video->user_id === auth()->id(), 403);

    $video->update([
        'generated_outline' => $request->validated()['outline'],
        'status' => 'outline_approved',
    ]);

    return to_route('videos.script', $video);
}
```

### Form Request: ApproveOutlineRequest

```php
class ApproveOutlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->id === $this->route('video')->user_id;
    }

    public function rules(): array
    {
        return [
            'outline' => ['required', 'array'],
            'outline.hook' => ['required', 'array'],
            'outline.hook.title' => ['required', 'string', 'max:200'],
            'outline.hook.duration_seconds' => ['required', 'integer', 'min:5', 'max:120'],
            'outline.hook.key_points' => ['required', 'array', 'min:1', 'max:10'],
            'outline.hook.key_points.*' => ['required', 'string', 'max:500'],
            'outline.hook.engagement_notes' => ['required', 'string', 'max:500'],
            'outline.sections' => ['required', 'array', 'min:1', 'max:10'],
            'outline.sections.*.title' => ['required', 'string', 'max:200'],
            'outline.sections.*.duration_seconds' => ['required', 'integer', 'min:10', 'max:600'],
            'outline.sections.*.key_points' => ['required', 'array', 'min:1', 'max:10'],
            'outline.sections.*.key_points.*' => ['required', 'string', 'max:500'],
            'outline.sections.*.engagement_notes' => ['required', 'string', 'max:500'],
            'outline.closing' => ['required', 'array'],
            'outline.closing.title' => ['required', 'string', 'max:200'],
            'outline.closing.duration_seconds' => ['required', 'integer', 'min:5', 'max:120'],
            'outline.closing.key_points' => ['required', 'array', 'min:1', 'max:10'],
            'outline.closing.key_points.*' => ['required', 'string', 'max:500'],
            'outline.closing.engagement_notes' => ['required', 'string', 'max:500'],
        ];
    }
}
```

### Route

```php
Route::patch('videos/{video}/outline', [OutlineController::class, 'approve'])->name('videos.outline.approve');
```

### Section IDs for DnD

Each section needs a unique ID for drag-and-drop tracking. Add an `id` field to sections on the frontend. Generate UUIDs client-side using `crypto.randomUUID()` when initializing the editor state. The IDs are only used client-side for DnD tracking and are not stored in the database.

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/outline.tsx` -- The outline page from E011-F001. Enhance with editing capabilities.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/video.ts` -- Video types. The `OutlineSection` and `GeneratedOutline` types are defined here.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/OutlineController.php` -- Outline controller from E011-F001. Add `approve` method.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Add approve route.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php` -- Video model. No changes needed (already has outline in fillable/casts).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card component for section containers.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/input.tsx` -- Input component for inline editing and duration.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button component for actions.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- cn() utility.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/OutlineGenerationTest.php` -- Outline tests from E011-F001. May extend with editing tests or create new file.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/outline-editor.tsx` -- The main outline editor component with drag-and-drop, inline editing, and section management.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/editable-section-card.tsx` -- Editable section card with inline title editing, key point management, and duration adjustment.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/inline-edit.tsx` -- Reusable inline text editor that toggles between display and input mode.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/ApproveOutlineRequest.php` -- Form request for validating the approved outline structure.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/OutlineEditingTest.php` -- Pest feature tests for outline editing and approval.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: outline-edit-backend-dev
    - Role: Creates the approve endpoint, ApproveOutlineRequest form request, and adds the approve route
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: outline-edit-frontend-dev
    - Role: Installs dnd-kit, creates the outline editor, editable section cards, inline edit component, and integrates with the outline page
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: outline-edit-test-dev
    - Role: Writes Pest feature tests for outline approval, validation, and authorization
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: outline-edit-reviewer
    - Role: Validates the complete outline editing implementation
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

### 1. Create Backend Approve Endpoint

- **Task ID**: create-approve-endpoint
- **Depends On**: none
- **Assigned To**: outline-edit-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/OutlineController.php` for existing controller structure
- Create `docker compose exec app php artisan make:request ApproveOutlineRequest --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/ApproveOutlineRequest.php` with the validation rules as described in Solution Approach
- Add `approve(ApproveOutlineRequest $request, Video $video): RedirectResponse` method to OutlineController:
    - Check ownership (abort 403)
    - Update video with validated outline and status `outline_approved`
    - Redirect to `videos.script` route (the script page from E011-F003)
- Add route to `/Users/young/Nextcloud/dev/Itervel/routes/web.php`: `Route::patch('videos/{video}/outline', [OutlineController::class, 'approve'])->name('videos.outline.approve');`
- Run `docker compose exec app vendor/bin/pint --dirty`

### 2. Create Frontend Outline Editor

- **Task ID**: create-outline-editor
- **Depends On**: create-approve-endpoint
- **Assigned To**: outline-edit-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Install dnd-kit: `npm install @dnd-kit/core @dnd-kit/sortable @dnd-kit/utilities`
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/inline-edit.tsx`:
    - Props: `value: string`, `onSave: (value: string) => void`, `className?: string`, `multiline?: boolean`
    - Toggles between display span (clickable) and Input (or textarea if multiline) on click
    - Saves on blur or Enter key, cancels on Escape
    - Uses `cn()` for styling
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/editable-section-card.tsx`:
    - Props: `section`, `onUpdate`, `onRemove?`, `isDraggable`, `variant: 'hook' | 'section' | 'closing'`
    - Renders Card with:
        - Drag handle (GripVertical icon) if isDraggable
        - InlineEdit for title
        - DurationInput (minutes + seconds inputs)
        - Remove button (Trash2 icon) if onRemove provided
        - EditableKeyPoints with add/remove/edit per point
        - InlineEdit for engagement notes (multiline)
    - Hook and closing variants get distinct border color styling
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/outline-editor.tsx`:
    - Props: `outline: GeneratedOutline`, `videoId: number`
    - Internal state manages hook, sections (with client-side UUIDs), and closing
    - Uses `DndContext` and `SortableContext` from dnd-kit for section reordering
    - Each section wrapped in `useSortable` from dnd-kit
    - Add section buttons between sections using an insert button
    - "Approve & Continue" button submits the full edited outline via Inertia `useForm` to the approve route
    - "Regenerate" button link to regenerate the outline
    - Calculates and displays total duration from all sections
- Update `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/outline.tsx`:
    - Import `OutlineEditor`
    - In the results state, render `OutlineEditor` instead of static section cards
    - Keep loading and error states unchanged
- Run `npm run build`
- Run `npm run types`
- Run `npm run lint`

### 3. Write Feature Tests

- **Task ID**: write-editing-tests
- **Depends On**: create-approve-endpoint
- **Assigned To**: outline-edit-test-dev
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with frontend)
- Create `docker compose exec app php artisan make:test OutlineEditingTest --pest --no-interaction`
- Write tests:
    - `test('user can approve an outline')` -- PATCH to approve route with valid outline data, assert redirect, assert status `outline_approved`, assert outline data saved
    - `test('approved outline replaces the previous outline')` -- PATCH with modified sections, assert `generated_outline` updated
    - `test('outline approval requires valid structure')` -- PATCH without hook, assert validation error
    - `test('section key points are required')` -- PATCH with empty key_points array, assert validation error
    - `test('section duration must be within range')` -- PATCH with duration 0 or 9999, assert validation error
    - `test('non-owner cannot approve outline')` -- assert 403
    - `test('unauthenticated user cannot approve outline')` -- assert redirect to login
    - `test('outline can have 1 to 10 sections')` -- PATCH with 1 section, assert OK; PATCH with 10 sections, assert OK; PATCH with 11 sections, assert validation error
- Run `docker compose exec app php artisan test tests/Feature/OutlineEditingTest.php --compact`
- Run `docker compose exec app php artisan test --compact`
- Run `docker compose exec app vendor/bin/pint --dirty`

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-approve-endpoint, create-outline-editor, write-editing-tests
- **Assigned To**: outline-edit-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands
- Verify the outline page switches to editor mode when outline is generated
- Verify inline editing works for titles, key points, and engagement notes
- Verify drag-and-drop reordering works for main sections (not hook/closing)
- Verify sections can be added and removed
- Verify duration inputs work correctly
- Verify approve saves the edited outline and transitions status
- Verify validation rejects invalid structures
- Verify all tests pass
- Confirm all acceptance criteria are met

## Acceptance Criteria

- The outline page displays an interactive editor when an outline is generated
- Section titles can be edited inline (click to edit, blur/Enter to save)
- Key points can be edited, added, and removed inline
- Engagement notes can be edited inline
- Main sections (not hook/closing) can be reordered via drag-and-drop
- New sections can be added between existing sections
- Sections can be removed (minimum 1 section required)
- Section duration can be adjusted via minute/second inputs
- Hook and closing cards are visually distinct and not draggable
- Total estimated duration updates in real-time as section durations change
- "Approve & Continue" saves the edited outline and transitions status to `outline_approved`
- Validation rejects outlines without a hook, closing, or at least 1 section
- Validation enforces field length and duration range limits
- Only video owners can approve (403 for others)
- dnd-kit is installed for drag-and-drop functionality
- All tests pass
- TypeScript compiles without errors
- ESLint passes
- PHP formatting passes

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run outline editing tests
docker compose exec app php artisan test tests/Feature/OutlineEditingTest.php --compact

# Run all outline tests
docker compose exec app php artisan test tests/Feature/Outline --compact

# Run full test suite
docker compose exec app php artisan test --compact

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# Prettier formatting check
npm run format:check

# PHP code formatting
docker compose exec app vendor/bin/pint --dirty
```

## Notes

- The `@dnd-kit` library is chosen over alternatives (react-beautiful-dnd, react-dnd) because it's actively maintained, supports React 19, has excellent accessibility (keyboard navigation, screen reader announcements), and is lightweight.
- Section IDs for drag-and-drop are generated client-side with `crypto.randomUUID()` and are ephemeral. They are NOT stored in the database. The backend only receives the outline structure without IDs.
- The hook and closing sections are fixed in position (first and last) and are not draggable. This prevents users from accidentally reordering them. They can still be edited inline.
- The InlineEdit component is intentionally kept simple. It uses a basic Input for single-line and a textarea for multiline. No rich text editing is needed for outline content.
- The duration inputs use separate minute and second fields for precision. The value is stored as total seconds in the data model.
- The "Regenerate" button on the editing page discards all edits and triggers a new outline generation (same as E011-F001). Users should be aware that regeneration replaces the current outline.

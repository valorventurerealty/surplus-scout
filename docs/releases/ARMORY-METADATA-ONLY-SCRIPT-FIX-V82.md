# Armory Metadata-Only Script Fix — V82

V82 fixes new Armory scripts appearing not to save when the user intends to build the script through guided steps instead of uploading a source file or pasting complete script text.

## Behavior

- A new script can be saved with its title, category, status, version, and optional description only.
- Script text and private source files remain supported but are optional.
- After creation, authorized users can open the Interactive Playbook Builder and add guided steps.
- Validation failures display a prominent message at the top of the form while retaining field-level errors.
- The create button is labeled **Save script**.

No database migration or frontend asset build is required.

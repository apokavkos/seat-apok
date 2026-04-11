
## 8. Dashboard Troubleshooting: "The 500 Crisis"
If the dashboard or nexus pages return 500 errors after an update:

### Vector F: Relationship Name Changes (v5)
- **Symptom**: `Call to undefined relationship [system] on model [CharacterLocation]`.
- **Cause**: Relationship names in SeAT v5 often differ from legacy versions.
- **Fix**: Use `solar_system` instead of `system` when eager-loading or accessing location data from `CharacterLocation` or `CharacterJumpClone`.

### Vector G: PHP Namespace Collisions
- **Symptom**: `FatalError: Cannot use CharacterInfo as CharacterInfo because the name is already in use`.
- **Cause**: Overwriting a controller and adding multiple `use` statements for the same class (often happens during rapid code insertion).
- **Fix**: Systematically check the top of the Controller for duplicate `use` declarations. Every model should be imported exactly once.

### Vector H: Preserving Logic Density
- **Rule**: The `DashboardController` is "Logic-Heavy." It handles wallets, corp filters, and industry slots simultaneously.
- **Danger**: Do NOT use destructive `cat` or `sed` commands that overwrite the entire file without including the original logic. Always merge new methods into the existing class structure.
- **Verification**: Always test BOTH the new page and the original "Overview" page after a change to ensure no logic was dropped.

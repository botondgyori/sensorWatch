1. Used Claude.ai with Sonnet 5(free version) to fix issues I had when something was wrong/not running (back in the old days this would have been StackOverflow)

2. Used Gemini PRO with Claude Opus 4.6 agent to help speed up development process but hand edited and reviewed all code

3. For "contigous dwell" I looked at timestamps: if the value stays out of the dead-band I trigger a state change, if a reading is inside the dead-band I reset the pending timer and cancel the pending transition leaving the sensor in its current state.

4. Edge case I ran into when handling values that land exactly on the treshold. The project setup description specifies >warnAbove and <clearBelow but when value is same as warnAbove it needs to be treated as dead-band and reset the pending timer.

5. The migration files AI suggested was to go with sequential versioning like 001_migration.php but I chose the timestamp version since working on the same project multiple developers that would create migration problems when having same version number like 001_create_tenants and 001_create_users.

6. 16 hours
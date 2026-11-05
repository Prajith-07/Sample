-- ============================================================================
-- iTech Seed Grand Challenge 2027 - Database Reset Script
-- Clears all data from all challenge application tables
-- ============================================================================

USE `itech_seed_2027`;

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `evaluations`;
TRUNCATE TABLE `proposal_budget_items`;
TRUNCATE TABLE `proposal_milestones`;
TRUNCATE TABLE `proposals`;
TRUNCATE TABLE `team_mentors`;
TRUNCATE TABLE `team_members`;
TRUNCATE TABLE `teams`;
TRUNCATE TABLE `registration_alerts`;

SET FOREIGN_KEY_CHECKS = 1;

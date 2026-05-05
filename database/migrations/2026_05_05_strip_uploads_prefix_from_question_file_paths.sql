-- Migration: Strip 'uploads/' prefix from question file paths
-- Date: 2026-05-05
-- Description: The Question model upload handlers previously stored paths with the
-- 'uploads/' prefix (e.g. 'uploads/questions/file.jpg'), but serveByPath at /api/res/{path}
-- prepends 'uploads/' when serving files, causing a double prefix. This migration updates
-- existing records to store paths without the 'uploads/' prefix, matching the pattern used
-- by FileController (e.g. 'questions/file.jpg').

START TRANSACTION;

-- Strip 'uploads/questions/' prefix from question_file column
UPDATE questions
SET question_file = REPLACE(question_file, 'uploads/questions/', 'questions/')
WHERE question_file LIKE 'uploads/questions/%';

-- Strip 'uploads/choices/' prefix from choice file columns
UPDATE questions
SET choice_A_file = REPLACE(choice_A_file, 'uploads/choices/', 'choices/')
WHERE choice_A_file LIKE 'uploads/choices/%';

UPDATE questions
SET choice_B_file = REPLACE(choice_B_file, 'uploads/choices/', 'choices/')
WHERE choice_B_file LIKE 'uploads/choices/%';

UPDATE questions
SET choice_C_file = REPLACE(choice_C_file, 'uploads/choices/', 'choices/')
WHERE choice_C_file LIKE 'uploads/choices/%';

UPDATE questions
SET choice_D_file = REPLACE(choice_D_file, 'uploads/choices/', 'choices/')
WHERE choice_D_file LIKE 'uploads/choices/%';

COMMIT;

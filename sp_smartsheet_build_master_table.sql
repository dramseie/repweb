DELIMITER $$

USE `nifi`$$

DROP PROCEDURE IF EXISTS `sp_smartsheet_build_master_table`$$

CREATE DEFINER=`webuser`@`%` PROCEDURE `sp_smartsheet_build_master_table`()
BEGIN
    DECLARE v_sql LONGTEXT;
    DECLARE v_columns LONGTEXT DEFAULT '';
    DECLARE v_column_id BIGINT;
    DECLARE v_column_title VARCHAR(255);
    DECLARE v_column_type VARCHAR(64);
    DECLARE v_safe_title VARCHAR(255);
    DECLARE v_alias VARCHAR(64);
    DECLARE v_suffix INT DEFAULT 1;
    DECLARE v_done INT DEFAULT FALSE;
    DECLARE v_table_name VARCHAR(64) DEFAULT 'smartsheet_master_data';
    DECLARE v_dupe_count INT DEFAULT 0;
    DECLARE v_value_expr LONGTEXT;

    -- Cursor to get ALL unique columns across ALL sheets with import_tasks = 1
    DECLARE col_cursor CURSOR FOR 
        SELECT 
            c.column_id,
            c.title,
            c.type
        FROM smartsheet_columns c
        INNER JOIN smartsheet_sheets s ON c.sheet_id = s.id
        WHERE s.import_tasks = 1
        GROUP BY c.title
        ORDER BY MIN(c.column_index), c.title;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

    DROP TEMPORARY TABLE IF EXISTS tmp_used_aliases;
    CREATE TEMPORARY TABLE tmp_used_aliases (
        alias_name VARCHAR(64) PRIMARY KEY
    );

    OPEN col_cursor;

    read_loop: LOOP
        FETCH col_cursor INTO v_column_id, v_column_title, v_column_type;
        IF v_done THEN
            LEAVE read_loop;
        END IF;

        SET v_safe_title = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            v_column_title, ' ', '_'), '-', '_'), '/', '_'), '(', ''), ')', ''), '.', '_'), ',', '');
        SET v_safe_title = REPLACE(REPLACE(REPLACE(v_safe_title, '__', '_'), '___', '_'), '____', '_');
        SET v_safe_title = LEFT(v_safe_title, 60);

        SET v_alias = LOWER(v_safe_title);
        SET v_suffix = 1;
        WHILE EXISTS (SELECT 1 FROM tmp_used_aliases WHERE alias_name = v_alias) DO
            SET v_suffix = v_suffix + 1;
            SET v_alias = CONCAT(LEFT(v_safe_title, 55), '_', v_suffix);
        END WHILE;

        INSERT INTO tmp_used_aliases(alias_name) VALUES (v_alias);

        SET v_value_expr = CONCAT(
            'NULLIF(COALESCE(',
                'JSON_UNQUOTE(JSON_EXTRACT(cell.value, ''$.displayValue'')), ',
                'JSON_UNQUOTE(JSON_EXTRACT(cell.value, ''$.value''))',
            '), '''')'
        );

        IF v_column_type LIKE '%date%' THEN
            SET v_columns = CONCAT(v_columns,
                'MAX(CASE WHEN col.title = ''', REPLACE(v_column_title, '''', ''''''), ''' ',
                'THEN STR_TO_DATE(',
                    'IF(LENGTH(', v_value_expr, ') = 10, ',
                        'CONCAT(', v_value_expr, ', '' 00:00:00''), ',
                        'REPLACE(', v_value_expr, ', ''T'', '' '' )',
                    '), ',
                    '''%Y-%m-%d %H:%i:%s''',
                ') END) AS `', v_alias, '`, '
            );
        ELSE
            SET v_columns = CONCAT(v_columns,
                'MAX(CASE WHEN col.title = ''', REPLACE(v_column_title, '''', ''''''), ''' ',
                'THEN ', v_value_expr, ' END) AS `', v_alias, '`, '
            );
        END IF;
    END LOOP;

    CLOSE col_cursor;
    DROP TEMPORARY TABLE IF EXISTS tmp_used_aliases;

    IF LENGTH(v_columns) > 2 THEN
        SET v_columns = LEFT(v_columns, LENGTH(v_columns) - 2);
    END IF;

    IF v_columns = '' THEN
        SELECT 'No columns found. Ensure sheets have import_tasks=1 and columns are imported.' AS error_message;
    ELSE
        SET @drop_sql = CONCAT('DROP TABLE IF EXISTS `', v_table_name, '`');
        PREPARE stmt FROM @drop_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SET v_sql = CONCAT(
            'CREATE TABLE `', v_table_name, '` AS ',
            'SELECT ',
                't.id AS task_id, ',
                't.sheet_id, ',
                's.name AS sheet_name, ',
                's.workspace_id, ',
                's.workspace_name, ',
                's.folder_id, ',
                's.folder_name, ',
                't.row_num, ',
                't.parent_id, ',
                't.indent, ',
                't.expanded, ',
                't.permalink, ',
                't.createdAt AS task_created, ',
                't.modifiedAt AS task_modified, ',
                v_columns, ' ',
            'FROM (',
                'SELECT * FROM (',
                    'SELECT t1.*, ',
                        'ROW_NUMBER() OVER (PARTITION BY t1.sheet_id, t1.id ',
                        'ORDER BY t1.modifiedAt DESC, t1.createdAt DESC, t1.row_num DESC) AS rn ',
                    'FROM smartsheet_task t1 ',
                ') ranked ',
                'WHERE ranked.rn = 1',
            ') t ',
            'INNER JOIN smartsheet_sheets s ON t.sheet_id = s.id ',
            'LEFT JOIN smartsheet_columns col ON col.sheet_id = t.sheet_id ',
            'LEFT JOIN JSON_TABLE( ',
                't.cells_data, ',
                '''$[*]'' COLUMNS( ',
                    'value JSON PATH ''$'' ',
                ') ',
            ') AS cell ON JSON_UNQUOTE(JSON_EXTRACT(cell.value, ''$.columnId'')) = col.column_id ',
            'WHERE s.import_tasks = 1 ',
                'AND t.cells_data IS NOT NULL ',
                'AND JSON_VALID(t.cells_data) ',
            'GROUP BY t.id, t.sheet_id, s.name, s.workspace_id, s.workspace_name, ',
                's.folder_id, s.folder_name, t.row_num, t.parent_id, t.indent, ',
                't.expanded, t.permalink, t.createdAt, t.modifiedAt ',
            'ORDER BY s.workspace_name, s.folder_name, s.name, t.row_num'
        );
        SET @sql = v_sql;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SELECT COUNT(*) INTO v_dupe_count
        FROM (
            SELECT 1
            FROM smartsheet_master_data
            GROUP BY sheet_id, task_id
            HAVING COUNT(*) > 1
        ) d;

        IF v_dupe_count > 0 THEN
            SELECT CONCAT('Duplicate (sheet_id, task_id) rows found: ', v_dupe_count) AS error_message;

            SELECT sheet_id, task_id, COUNT(*) AS dupes
            FROM smartsheet_master_data
            GROUP BY sheet_id, task_id
            HAVING COUNT(*) > 1
            ORDER BY dupes DESC, sheet_id, task_id
            LIMIT 50;
        ELSE
            SET @idx1 = CONCAT('ALTER TABLE `', v_table_name, '` ADD PRIMARY KEY (sheet_id, task_id)');
            PREPARE stmt FROM @idx1;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;

            SET @idx2 = CONCAT('ALTER TABLE `', v_table_name, '` ADD INDEX idx_sheet (sheet_id)');
            PREPARE stmt FROM @idx2;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;

            SET @idx3 = CONCAT('ALTER TABLE `', v_table_name, '` ADD INDEX idx_workspace (workspace_id)');
            PREPARE stmt FROM @idx3;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;

            SET @idx4 = CONCAT('ALTER TABLE `', v_table_name, '` ADD INDEX idx_modified (task_modified)');
            PREPARE stmt FROM @idx4;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;

            SET @idx5 = CONCAT('ALTER TABLE `', v_table_name, '` ADD INDEX idx_parent (parent_id)');
            PREPARE stmt FROM @idx5;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;

            SET @outofscope_drop_sql = 'DROP TABLE IF EXISTS `smartsheet_master_data_outofscope`';
            PREPARE stmt FROM @outofscope_drop_sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;

            SET @outofscope_sql = CONCAT('CREATE TABLE `smartsheet_master_data_outofscope` LIKE `', v_table_name, '`');
            PREPARE stmt FROM @outofscope_sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;

            INSERT INTO `smartsheet_master_data_outofscope`
            SELECT * FROM `smartsheet_master_data`
            WHERE `in_scope` = 'Hold';

            DELETE FROM `smartsheet_master_data`
            WHERE `in_scope` = 'Hold';

            CREATE TABLE IF NOT EXISTS `smartsheet_master_data_history` (
                sheet_id BIGINT NULL,
                sheet_name VARCHAR(255) NULL,
                task_id BIGINT NULL,
                task_name VARCHAR(255) NULL,
                site_id VARCHAR(255) NULL,
                site_name VARCHAR(255) NULL,
                start_date DATE NULL,
                end_date DATE NULL,
                ts DATETIME NOT NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

            INSERT INTO `smartsheet_master_data_history` (
                sheet_id,
                sheet_name,
                task_id,
                task_name,
                site_id,
                site_name,
                start_date,
                end_date,
                ts
            )
            SELECT
                sheet_id,
                sheet_name,
                task_id,
                task_name,
                site_id,
                site_name,
                start_date,
                end_date,
                NOW()
            FROM `smartsheet_master_data`;

            SELECT 
                v_table_name AS table_created,
                (SELECT COUNT(*) FROM smartsheet_master_data) AS total_rows,
                (SELECT COUNT(DISTINCT sheet_id) FROM smartsheet_master_data) AS sheets_included,
                (SELECT COUNT(DISTINCT workspace_id) FROM smartsheet_master_data) AS workspaces_included,
                NOW() AS created_at;
        END IF;
    END IF;
END$$

DELIMITER ;

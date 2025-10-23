-- runbook_templates_reinstall.sql
-- Apply after 004_optional_runbook_templates.sql
INSERT INTO `repweb_mig`.`mig_runbook_template` (`name`,`method`,`description`,`version`)
VALUES ('Reinstall – Standard', 'Reinstall', 'Fresh OS+App reinstall, then data restore', 1);

SET @tid = LAST_INSERT_ID();
INSERT INTO `repweb_mig`.`mig_runbook_template_step` (`template_id`,`seq`,`phase`,`title`,`details`,`est_minutes`) VALUES
(@tid, 10,'Pre','Backup verified','Restore test in lower env referenced',15),
(@tid, 20,'Pre','Golden image ready','OS baseline & hardening OK',10),
(@tid, 30,'Pre','Config export','All config files exported and versioned',15),
(@tid, 40,'Freeze','Notify stakeholders','Change window starts, freeze writes',5),
(@tid, 50,'Cutover','Provision target','VM/host prepared with correct sizing',30),
(@tid, 60,'Cutover','Harden & patch','Baseline hardening profile applied',20),
(@tid, 70,'Cutover','Install application','Deploy from artifact repo',40),
(@tid, 80,'Cutover','Restore data','Import DB/files and sync',60),
(@tid, 90,'Post','Smoke tests','Basic functional validation',20),
(@tid,100,'Post','Monitoring re-registered','Ensure metrics & alerts green',10),
(@tid,110,'Post','DNS/GLB flip','Traffic to target',5),
(@tid,120,'Post','Business validation','Owner acceptance recorded',10),
(@tid,130,'Backout','Backout plan','If validation fails, restore source',0);

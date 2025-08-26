
-- Sample menu data tailored to repweb sections
DELETE FROM menu_item;

INSERT INTO menu_item (id,label,url,route,route_params,parent_id,position,mega_group,is_active,roles,icon,external) VALUES
  (1,'Overview','/',NULL,NULL,NULL,1,NULL,1,'[]','bi bi-house',0),
  (2,'Analytics',NULL,NULL,NULL,NULL,2,NULL,1,'[]',NULL,0),
  (3,'Visualizations',NULL,NULL,NULL,2,1,'Visualizations',1,'[]',NULL,0),
  (4,'DataTables',NULL,'reports_datatables','{}',3,1,NULL,1,'[]','bi bi-table',0),
  (5,'Plotly',NULL,'reports_plotly','{}',3,2,NULL,1,'[]','bi bi-activity',0),
  (6,'Grafana','https://grafana.example.com',NULL,NULL,3,3,NULL,1,'["ROLE_USER"]','bi bi-bar-chart',1),
  (7,'PivotTables',NULL,'reports_pivottables','{}',3,4,NULL,1,'[]','bi bi-grid-3x3-gap',0),
  (8,'Tools',NULL,NULL,NULL,2,2,'Utilities',1,'[]',NULL,0),
  (9,'External Links',NULL,'external_links','{}',8,1,NULL,1,'[]','bi bi-box-arrow-up-right',0),
  (10,'Send E-mail','mailto:support@example.com',NULL,NULL,8,2,NULL,1,'[]','bi bi-envelope',1),
  (11,'Sources',NULL,NULL,NULL,2,3,'Sources',1,'[]',NULL,0),
  (12,'OpsRamp',NULL,'sources_opsramp','{}',11,1,NULL,1,'[]','bi bi-cloud',0),
  (13,'ServiceNow',NULL,'sources_servicenow','{}',11,2,NULL,1,'[]','bi bi-briefcase',0),
  (14,'vCenter',NULL,'sources_vcenter','{}',11,3,NULL,1,'[]','bi bi-cpu',0),
  (15,'OneView',NULL,'sources_oneview','{}',11,4,NULL,1,'[]','bi bi-hdd-network',0);

ALTER TABLE menu_item AUTO_INCREMENT = 1000;

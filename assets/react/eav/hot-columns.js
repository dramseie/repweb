// assets/react/eav/hot-columns.js
export const typeCols = [
  { data: 'id', readOnly: true, width: 60 },
  { data: 'code', type: 'text' },
  { data: 'name', type: 'text' },
  { data: 'icon', type: 'text' },
  { data: '__actions', readOnly: true, renderer: 'html', width: 110 }, // “Attributes” button
];

export const attrCols = [
  { data: 'id', readOnly: true, width: 60 },
  { data: 'code', type: 'text' },
  { data: 'label', type: 'text' },
  { data: 'data_type', type: 'dropdown', source: ['string','text','integer','decimal','boolean','datetime','json','reference','ip','cidr'] },
  { data: 'unit', type: 'text' },
  { data: 'description', type: 'text' },
  { data: 'is_searchable', type: 'checkbox' },
  { data: 'is_indexed', type: 'checkbox' },
  { data: 'rbac_visibility', type: 'dropdown', source: ['tenant','public','private'] },
  { data: 'owner_role_id', type: 'numeric' },
];

import React, { useEffect, useState } from "react";

export default function MailTemplateList({ onSelect }) {
  const [templates, setTemplates] = useState([]);

  useEffect(() => {
    fetch("/api/mail/templates")
      .then(r => r.json())
      .then(setTemplates)
      .catch(err => console.error("Load templates failed", err));
  }, []);

  return (
    <div>
      <h2>Mail Templates</h2>
      <button className="btn btn-success mb-2" onClick={() => onSelect(null)}>
        ➕ New Template
      </button>
      <table className="table table-sm table-striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Subject</th>
            <th>Active</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {templates.map(tpl => (
            <tr key={tpl.id}>
              <td>{tpl.id}</td>
              <td>{tpl.NAME}</td>
              <td>{tpl.SUBJECT}</td>
              <td>{tpl.is_active ? "✔️" : "❌"}</td>
              <td>
                <button
                  className="btn btn-sm btn-primary"
                  onClick={() => onSelect(tpl)}
                >
                  Edit
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

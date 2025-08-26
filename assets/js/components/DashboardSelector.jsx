
        import React, { useEffect, useState } from 'react';
        export default function DashboardSelector() {
          const [dashboards, setDashboards] = useState([]);
          useEffect(() => { fetch('/api/grafana/dashboards').then(r=>r.json()).then(setDashboards); }, []);
          if (!dashboards.length) return <p>No dashboards available.</p>;
          return (<div><h3>Available Grafana Dashboards</h3><ul>
            {dashboards.map(d => (<li key={d.uid}><a href={`/grafana/${d.uid}`}>{d.title}</a></li>))}
          </ul></div>);
        }
        
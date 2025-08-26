import React from 'react';

export default function TileCard({ userTile, onRemove }) {
  const t = userTile.tile;
  const cfg = t.config || {};

  let body;
  switch (t.type) {
    case 'link':
      body = (
        <a className="stretched-link" href={cfg.url || '#'} target={cfg.target || '_self'} rel="noreferrer">
          {cfg.subtitle || cfg.url}
        </a>
      );
      break;
    case 'grafana':
      body = (
        <div className="ratio ratio-16x9">
          <iframe title={t.title} src={cfg.src} frameBorder="0" allowFullScreen />
        </div>
      );
      break;
    case 'iframe':
      body = (
        <div className="ratio ratio-16x9">
          <iframe title={t.title} src={cfg.src} frameBorder="0" />
        </div>
      );
      break;
    case 'image':
      body = (<img src={cfg.src} alt={t.title} className="img-fluid" />);
      break;
    case 'text':
      body = (<div dangerouslySetInnerHTML={{ __html: cfg.html || '' }} />);
      break;
    default:
      body = (<div className="text-muted">Unsupported type: {t.type}</div>);
  }

	return (
	  <div className="card h-100 shadow-sm tile-card">   {/* <-- tile-card class */}
		{t.thumbnailUrl && (<img src={t.thumbnailUrl} className="card-img-top" alt="thumb" />)}
		<div className="card-body">
		  <h5 className="card-title">{t.title}</h5>
		  <div className="card-text">{body}</div>
		</div>
		<div className="card-footer bg-white border-0 d-flex justify-content-end gap-2">
		  <button className="btn btn-outline-danger btn-sm" onClick={() => onRemove(userTile.id)}>
			Remove
		  </button>
		</div>
	  </div>
	);

}

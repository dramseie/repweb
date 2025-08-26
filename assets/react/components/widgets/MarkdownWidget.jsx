import React from 'react';

export default function MarkdownWidget({ md = 'Hello **repweb**!' }) {
  const html = md
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\n/g, '<br/>');
  // eslint-disable-next-line react/no-danger
  return <div dangerouslySetInnerHTML={{ __html: html }} />;
}

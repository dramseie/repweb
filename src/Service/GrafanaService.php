<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GrafanaService
{
    public function __construct(private HttpClientInterface $http) {}

    /* -------------------- Basics -------------------- */

    public function grafanaBase(): string
    {
        return rtrim($_ENV['GRAFANA_BASE_URL'] ?? '', '/');
    }

    public function grafanaOrg(): string
    {
        return (string)($_ENV['GRAFANA_ORG_ID'] ?? '1');
    }

    private function apiToken(): string
    {
        return (string)($_ENV['GRAFANA_API_TOKEN'] ?? '');
    }

    /** GET wrapper that fails loudly with clear info */
    private function apiGet(string $path, array $query = []): array
    {
        $resp = $this->http->request('GET', $this->grafanaBase() . $path, [
            'headers' => [
                'Authorization'    => 'Bearer ' . $this->apiToken(),
                'Accept'           => 'application/json',
                'X-Grafana-Org-Id' => $this->grafanaOrg(),
            ],
            'query' => $query,
        ]);

        $status = $resp->getStatusCode();
        $text   = $resp->getContent(false);

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(sprintf('Grafana GET %s failed (%d): %s', $path, $status, mb_substr($text, 0, 500)));
        }

        $data = json_decode($text, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(sprintf('Grafana GET %s returned non-JSON: %s', $path, mb_substr($text, 0, 500)));
        }

        return $data;
    }

    /* -------------------- Folders & Dashboards -------------------- */

    /**
     * List Grafana folders (Viewer can read via /api/search?type=dash-folder).
     * @return array<int,array{id:int,title:string,uid:?string}>
     */
    public function listFolders(): array
    {
        $data = $this->apiGet('/api/search', [
            'type'  => 'dash-folder',
            'limit' => 500,
        ]);

        $list = [];
        foreach ($data as $row) {
            if (!is_array($row)) continue;
            $id  = $row['id']  ?? null;
            $uid = $row['uid'] ?? null;
            $ttl = $row['title'] ?? 'Folder';
            if ($id !== null) {
                $list[] = ['id' => (int)$id, 'title' => (string)$ttl, 'uid' => $uid ? (string)$uid : null];
            }
        }
        // Sort by title
        usort($list, fn($a,$b)=>strcmp($a['title'],$b['title']));
        return $list;
    }

    /**
     * List Grafana dashboards; optional allow-list + folder filter.
     *
     * @param array<string>|null $roles     (reserved for future RBAC logic)
     * @param array<string>|null $allowUids allow-listed dashboard UIDs
     * @param array<int>|null    $folderIds filter by folderId(s)
     * @return array<int,array{uid:string,title:string,slug:?string,folderId:?int}>
     */
    public function listDashboardsForUser(?array $roles = null, ?array $allowUids = null, ?array $folderIds = null): array
    {
        $data = $this->apiGet('/api/search', [
            'type'  => 'dash-db',
            'limit' => 1000,
        ]);

        // Keep only items that actually have a UID
        $filtered = array_values(array_filter($data, fn ($d) => is_array($d) && !empty($d['uid'])));

        // Optional: filter by folder IDs if provided
        if ($folderIds && count($folderIds) > 0) {
            $set = array_flip(array_map('intval', $folderIds));
            $filtered = array_values(array_filter($filtered, function ($d) use ($set) {
                $fid = isset($d['folderId']) ? (int)$d['folderId'] : null;
                return $fid !== null ? isset($set[$fid]) : false;
            }));
        }

        $list = array_map(function ($d) {
            $url   = $d['url'] ?? '';
            // URL shape: /d/<uid>/<slug>
            $parts = explode('/', trim($url, '/'));
            $slug  = $parts[2] ?? null;

            return [
                'uid'      => (string)$d['uid'],
                'title'    => $d['title'] ?? 'Untitled',
                'slug'     => $slug,
                'folderId' => isset($d['folderId']) ? (int)$d['folderId'] : null,
            ];
        }, $filtered);

        // Apply allow-list filtering if provided
        if ($allowUids && is_array($allowUids)) {
            $allowUids = array_map('strval', $allowUids);
            $list = array_values(array_filter($list, fn ($d) => in_array($d['uid'], $allowUids, true)));
        }

        return $list;
    }

    /* -------------------- Panels & Variables -------------------- */

    private function slugFromDash(array $payload, string $fallback = ''): string
    {
        if (!empty($payload['meta']['slug'])) {
            return $payload['meta']['slug'];
        }
        if (!empty($payload['dashboard']['title'])) {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $payload['dashboard']['title']), '-'));
            return $slug ?: $fallback;
        }
        return $fallback;
    }

    private function flattenPanels(array $panels): array
    {
        $out = [];
        $walker = function ($p) use (&$out, &$walker) {
            if (($p['type'] ?? '') === 'row' && !empty($p['panels'])) {
                foreach ($p['panels'] as $c) $walker($c);
                return;
            }
            if (isset($p['id'])) {
                $out[] = [
                    'id'    => (int)$p['id'],
                    'title' => $p['title'] ?? ('Panel ' . $p['id']),
                    'type'  => $p['type'] ?? 'graph',
                ];
            }
        };
        foreach ($panels as $p) $walker($p);
        return $out;
    }

    /**
     * @return array{
     *   uid:string, slug:string, title:string,
     *   panels:array<int,array{id:int,title:string,type:string}>
     * }
     */
    public function dashboardPanels(string $uid): array
    {
        $payload = $this->apiGet('/api/dashboards/uid/' . rawurlencode($uid));
        $slug    = $this->slugFromDash($payload, $uid);
        $panels  = $this->flattenPanels($payload['dashboard']['panels'] ?? []);
        $title   = $payload['dashboard']['title'] ?? $uid;

        return compact('uid','slug','title','panels');
    }

    /**
     * Extract dashboard variables from /api/dashboards/uid/:uid
     *
     * Returns an array like:
     * [
     *   {
     *     name: "host_name",
     *     type: "query|custom|constant|textbox|datasource|interval",
     *     label: "Host",
     *     multi: true|false,
     *     includeAll: true|false,
     *     current: { value: string|array|null, text: string|array|null },
     *     query: string|null,
     *     datasource: mixed|null,
     *     options: array<int, array{text:mixed,value:mixed,selected?:bool}>
     *   }, ...
     * ]
     *
     * @return array<int,array{
     *   name:string,type:string,label:string,multi:bool,includeAll:bool,
     *   current:array{value:mixed,text:mixed},
     *   query:mixed,datasource:mixed,options:array<int,array{text:mixed,value:mixed,selected:bool}>
     * }>
     */
    public function dashboardVariables(string $uid): array
    {
        $payload = $this->apiGet('/api/dashboards/uid/' . rawurlencode($uid));
        $vars = $payload['dashboard']['templating']['list'] ?? [];
        $out  = [];

        foreach ($vars as $v) {
            if (!is_array($v) || empty($v['name'])) {
                continue;
            }

            // Normalize current value/text
            $currentValue = $v['current']['value'] ?? null;
            $currentText  = $v['current']['text']  ?? null;

            // Normalize options (may be empty for query variables resolved client-side)
            $options = [];
            if (!empty($v['options']) && is_array($v['options'])) {
                foreach ($v['options'] as $o) {
                    if (!is_array($o)) continue;
                    $options[] = [
                        'text'     => $o['text']  ?? ($o['value'] ?? ''),
                        'value'    => $o['value'] ?? ($o['text']  ?? ''),
                        'selected' => (bool)($o['selected'] ?? false),
                    ];
                }
            }

            $out[] = [
                'name'       => (string)$v['name'],
                'type'       => (string)($v['type'] ?? 'query'),
                'label'      => isset($v['label']) ? (string)$v['label'] : (string)$v['name'],
                'multi'      => (bool)($v['multi'] ?? false),
                'includeAll' => (bool)($v['includeAll'] ?? false),
                'current'    => [
                    'value' => $currentValue,
                    'text'  => $currentText,
                ],
                'query'      => $v['query']      ?? null,
                'datasource' => $v['datasource'] ?? null,
                'options'    => $options,
            ];
        }

        return $out;
    }

    /* -------------------- URL helpers -------------------- */

    /**
     * @param array<string,string|int> $params e.g. ['orgId'=>1,'from'=>...,'to'=>...]
     */
    public function buildIframeUrl(string $uid, string $slug, array $params, ?int $panelId = null): string
    {
        $base = $this->grafanaBase();
        $qs   = http_build_query($params);

        if ($panelId !== null) {
            return sprintf('%s/d-solo/%s/%s?%s&panelId=%d', $base, rawurlencode($uid), rawurlencode($slug), $qs, $panelId);
        }
        return sprintf('%s/d/%s/%s?%s', $base, rawurlencode($uid), rawurlencode($slug), $qs);
    }

    /** Simple helper when only UID is known. */
    public function buildEmbedUrl(string $uid): string
    {
        return sprintf('%s/d/%s?orgId=%s', $this->grafanaBase(), rawurlencode($uid), rawurlencode($this->grafanaOrg()));
    }
	
	// in GrafanaService

	private function apiPost(string $path, array $json): array {
		$resp = $this->http->request('POST', $this->grafanaBase().$path, [
			'headers' => [
				'Authorization'    => 'Bearer '.$this->apiToken(),
				'Accept'           => 'application/json',
				'Content-Type'     => 'application/json',
				'X-Grafana-Org-Id' => $this->grafanaOrg(),
			],
			'json' => $json,
		]);
		return $resp->toArray(false);
	}

	/** Grab strings from Grafana's DataFrame-ish result */
	private function extractStringsFromDsResult(array $result): array {
		$out = [];

		// New style: results -> <refId> -> frames[n] -> data.values[columns]
		if (!empty($result['results']) && is_array($result['results'])) {
			foreach ($result['results'] as $res) {
				foreach (($res['frames'] ?? []) as $frame) {
					$cols = $frame['data']['values'] ?? [];
					foreach ($cols as $col) {
						if (is_array($col)) {
							foreach ($col as $v) {
								if (is_string($v) || is_numeric($v)) $out[] = (string)$v;
							}
						}
					}
				}
				// Legacy: tables/rows
				foreach (($res['tables'] ?? []) as $t) {
					foreach (($t['rows'] ?? []) as $row) {
						foreach ($row as $v) {
							if (is_string($v) || is_numeric($v)) $out[] = (string)$v;
						}
					}
				}
			}
		}
		return array_values(array_unique($out));
	}

	/** Resolve options for one variable by name */
	public function resolveVariableOptions(string $dashUid, string $varName): array {
		$dash = $this->apiGet('/api/dashboards/uid/'.rawurlencode($dashUid));
		$vars = $dash['dashboard']['templating']['list'] ?? [];
		$var  = null;
		foreach ($vars as $v) {
			if (isset($v['name']) && strcasecmp($v['name'], $varName) === 0) { $var = $v; break; }
		}
		if (!$var) throw new \RuntimeException("Variable '$varName' not found.");

		// Easy types first
		switch ($var['type'] ?? 'query') {
			case 'custom':   // comma-separated string in $var['query']
				return array_values(array_filter(array_map('trim', explode(',', (string)($var['query'] ?? '')))));
			case 'constant':
				return [(string)($var['query'] ?? '')];
			case 'textbox':
				return []; // free-text, no predefined options
			case 'datasource':
				// list datasources, optionally filter by plugin/type regex present in the var
				$all = $this->apiGet('/api/datasources');
				return array_map(fn($d) => (string)$d['name'], $all);
			default:
				// query variables → call /api/ds/query
				$dsUid = $var['datasource']['uid'] ?? null;
				if (!$dsUid) throw new \RuntimeException("Variable '$varName' has no datasource uid.");

				// Best-effort generic payload. For some plugins you must mirror the UI payload.
				$query = $var['definition'] ?? $var['query'] ?? '';
				$body = [
					'from'    => 'now-1h',
					'to'      => 'now',
					'queries' => [[
						'refId'      => 'Var',
						'datasource' => ['uid' => $dsUid],
						'queryType'  => 'variable',
						// Generic fields – plugin may need different keys (expr, query, target, etc.)
						'expr'       => $query,
						'query'      => $query,
					]],
				];

				$res  = $this->apiPost('/api/ds/query', $body);
				$vals = $this->extractStringsFromDsResult($res);
				return $vals;
		}
	}

}

<?php
namespace App\Command;

use App\Service\RouteTreeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'app:routes:check', description: 'Check safe routes (HEAD→GET) and print summary')]
final class CheckRoutesCommand extends Command
{
    public function __construct(
        private RouteTreeService $routes,
        private HttpClientInterface $http
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addArgument('timeout', InputArgument::OPTIONAL, 'Timeout seconds per request', '5.0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timeout = (float) $input->getArgument('timeout');

        $base = $this->routes->getProbeBase();
        $io->writeln("<comment>Probing base:</comment> {$base}");

        $list = $this->routes->listRoutes()['flat'];

        $okCnt = 0; $failCnt = 0;
        foreach ($list as $r) {
            if (!$r['hasSafe'] || !$r['canGenerate'] || empty($r['url'])) {
                $io->writeln(sprintf('<comment>SKIP</comment> %-40s %s', $r['name'], $r['path']));
                continue;
            }

            $status = null; $ok = false; $err = null;
            try {
                $resp = $this->http->request('HEAD', $r['url'], ['timeout' => $timeout, 'max_redirects' => 3]);
                $status = $resp->getStatusCode();
                $ok = $status >= 200 && $status < 400;
                if (!$ok && in_array($status, [403,405,501], true)) {
                    $resp = $this->http->request('GET', $r['url'], ['timeout' => $timeout, 'max_redirects' => 3]);
                    $status = $resp->getStatusCode();
                    $ok = $status >= 200 && $status < 400;
                }
            } catch (\Throwable $e) {
                $err = $e->getMessage();
            }

            if ($ok) { $okCnt++; $tag = '<info>OK</info>'; }
            else     { $failCnt++; $tag = '<error>FAIL</error>'; }

            $io->writeln(sprintf('%s %-40s [%s] %s %s',
                $tag, $r['name'], $status ?? 'ERR', $r['path'], $err ? ('— '.$err) : ''
            ));
        }

        $io->success(sprintf('Done: OK=%d, FAIL=%d', $okCnt, $failCnt));
        return $failCnt ? Command::FAILURE : Command::SUCCESS;
    }
}

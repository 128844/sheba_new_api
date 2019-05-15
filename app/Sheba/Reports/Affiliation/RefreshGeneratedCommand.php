<?php namespace Sheba\Reports\Affiliation;

use Illuminate\Console\Command;

class RefreshGeneratedCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'sheba:refresh-affiliation-report {skip=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh affiliation report.';

    private $generator;

    /**
     * Create a new migration install command instance.
     *
     * @param Generator $generator
     */
    public function __construct(Generator $generator)
    {
        parent::__construct();
        $this->generator = $generator;
    }

    public function handle()
    {
        $this->generator->refresh((int)$this->argument('skip'));
        $this->info('Done.');
    }
}
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'timesheet_contract_approval', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_contract_month', columns: ['contract_id', 'report_month'])])]
class TimesheetContractApproval
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TimesheetContract::class)]
    #[ORM\JoinColumn(name: 'contract_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?TimesheetContract $contract = null;

    #[ORM\Column(name: 'report_month', type: 'string', length: 7)]
    private string $reportMonth;

    #[ORM\Column(name: 'approved_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $approvedAt = null;

    #[ORM\Column(name: 'approved_by', type: 'string', length: 190, nullable: true)]
    private ?string $approvedBy = null;

    #[ORM\Column(name: 'comment', type: 'text', nullable: true)]
    private ?string $comment = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContract(): ?TimesheetContract
    {
        return $this->contract;
    }

    public function setContract(TimesheetContract $contract): self
    {
        $this->contract = $contract;
        return $this;
    }

    public function getReportMonth(): string
    {
        return $this->reportMonth;
    }

    public function setReportMonth(string $reportMonth): self
    {
        $this->reportMonth = $reportMonth;
        return $this;
    }

    public function getApprovedAt(): ?\DateTimeInterface
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeInterface $approvedAt): self
    {
        $this->approvedAt = $approvedAt;
        return $this;
    }

    public function getApprovedBy(): ?string
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?string $approvedBy): self
    {
        $this->approvedBy = $approvedBy;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }
}

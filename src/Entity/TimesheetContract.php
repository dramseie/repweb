<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'timesheet_contract')]
class TimesheetContract
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(name: 'project_name', type: 'string', length: 190)]
    private string $projectName;

    #[ORM\Column(name: 'po_number', type: 'string', length: 64, nullable: true)]
    private ?string $poNumber = null;

    #[ORM\Column(name: 'supplier', type: 'string', length: 190)]
    private string $supplier;

    #[ORM\Column(name: 'workload_hours_week', type: 'decimal', precision: 6, scale: 2)]
    private string $workloadHoursWeek = '0.00';

    #[ORM\Column(name: 'from_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeInterface $fromDate = null;

    #[ORM\Column(name: 'to_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeInterface $toDate = null;

    #[ORM\Column(name: 'total_hours', type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $totalHours = null;

    #[ORM\Column(name: 'customer_approval_emails', type: 'text', nullable: true)]
    private ?string $customerApprovalEmails = null;

    #[ORM\Column(name: 'supplier_timesheet_receiver_email', type: 'string', length: 190, nullable: true)]
    private ?string $supplierTimesheetReceiverEmail = null;

    #[ORM\Column(name: 'contract_pdf_path', type: 'string', length: 255, nullable: true)]
    private ?string $contractPdfPath = null;

    #[ORM\Column(name: 'billing_frequency', type: 'string', length: 32)]
    private string $billingFrequency = 'monthly';

    #[ORM\Column(name: 'requires_signed_report', type: 'boolean')]
    private bool $requiresSignedReport = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProjectName(): string
    {
        return $this->projectName;
    }

    public function setProjectName(string $projectName): self
    {
        $this->projectName = $projectName;
        return $this;
    }

    public function getPoNumber(): ?string
    {
        return $this->poNumber;
    }

    public function setPoNumber(?string $poNumber): self
    {
        $this->poNumber = $poNumber;
        return $this;
    }

    public function getSupplier(): string
    {
        return $this->supplier;
    }

    public function setSupplier(string $supplier): self
    {
        $this->supplier = $supplier;
        return $this;
    }

    public function getWorkloadHoursWeek(): string
    {
        return $this->workloadHoursWeek;
    }

    public function setWorkloadHoursWeek(string $workloadHoursWeek): self
    {
        $this->workloadHoursWeek = $workloadHoursWeek;
        return $this;
    }

    public function getFromDate(): ?\DateTimeInterface
    {
        return $this->fromDate;
    }

    public function setFromDate(?\DateTimeInterface $fromDate): self
    {
        $this->fromDate = $fromDate;
        return $this;
    }

    public function getToDate(): ?\DateTimeInterface
    {
        return $this->toDate;
    }

    public function setToDate(?\DateTimeInterface $toDate): self
    {
        $this->toDate = $toDate;
        return $this;
    }

    public function getTotalHours(): ?string
    {
        return $this->totalHours;
    }

    public function setTotalHours(?string $totalHours): self
    {
        $this->totalHours = $totalHours;
        return $this;
    }

    public function getCustomerApprovalEmails(): ?string
    {
        return $this->customerApprovalEmails;
    }

    public function setCustomerApprovalEmails(?string $customerApprovalEmails): self
    {
        $this->customerApprovalEmails = $customerApprovalEmails;
        return $this;
    }

    public function getSupplierTimesheetReceiverEmail(): ?string
    {
        return $this->supplierTimesheetReceiverEmail;
    }

    public function setSupplierTimesheetReceiverEmail(?string $supplierTimesheetReceiverEmail): self
    {
        $this->supplierTimesheetReceiverEmail = $supplierTimesheetReceiverEmail;
        return $this;
    }

    public function getContractPdfPath(): ?string
    {
        return $this->contractPdfPath;
    }

    public function setContractPdfPath(?string $contractPdfPath): self
    {
        $this->contractPdfPath = $contractPdfPath;
        return $this;
    }

    public function getBillingFrequency(): string
    {
        return $this->billingFrequency;
    }

    public function setBillingFrequency(string $billingFrequency): self
    {
        $this->billingFrequency = $billingFrequency;
        return $this;
    }

    public function requiresSignedReport(): bool
    {
        return $this->requiresSignedReport;
    }

    public function setRequiresSignedReport(bool $requiresSignedReport): self
    {
        $this->requiresSignedReport = $requiresSignedReport;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}

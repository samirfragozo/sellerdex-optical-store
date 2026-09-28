/** The POS prescription step's list entry: an existing prescription on page load, or the one just saved. */
export interface PrescriptionOption {
    id: number;
    customer_id: number;
    exam_date: string | null;
    expires_at: string | null;
    is_expired: boolean;
    summary: string;
}

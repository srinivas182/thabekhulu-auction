import { router } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Visit = { url: URL | string; method: string; data: unknown };

/**
 * Security approval codes: when a back-office action comes back asking for a code, this asks for it and
 * re-sends the very same request with the code. One dialog serves every protected action.
 */
export default function ApprovalCodeDialog() {
    const last = useRef<Visit | null>(null);
    const [open, setOpen] = useState(false);
    const [message, setMessage] = useState('');
    const [code, setCode] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        const offBefore = router.on('before', (e) => {
            const v = e.detail.visit as unknown as Visit;
            if (String(v.method).toLowerCase() !== 'get') {
                last.current = { url: v.url, method: v.method, data: v.data };
            }
        });
        const offError = router.on('error', (e) => {
            const m = (e.detail.errors as Record<string, string>).approval_code;
            if (m) {
                setMessage(m);
                setOpen(true);
            }
        });

        return () => {
            offBefore();
            offError();
        };
    }, []);

    const submit = () => {
        const visit = last.current;
        if (!visit || code.trim() === '') return;
        let data: unknown;
        if (visit.data instanceof FormData) {
            const fd = new FormData();
            visit.data.forEach((v, k) => fd.append(k, v));
            fd.set('approval_code', code.trim());
            data = fd;
        } else {
            data = {
                ...(visit.data as Record<string, unknown>),
                approval_code: code.trim(),
            };
        }
        setBusy(true);
        router.visit(
            typeof visit.url === 'string' ? visit.url : visit.url.href,
            {
                method: visit.method.toLowerCase() as
                    | 'post'
                    | 'put'
                    | 'patch'
                    | 'delete',
                data: data as never,
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setOpen(false);
                    setCode('');
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && setOpen(false)}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 font-display text-2xl">
                        <ShieldCheck
                            className="size-6 text-primary"
                            aria-hidden="true"
                        />
                        Approval needed
                    </DialogTitle>
                    <DialogDescription>
                        For security, this action needs a code from a security
                        approver. They’ve been emailed what you’re doing, with
                        your name and the time; ask one of them for the code.
                    </DialogDescription>
                </DialogHeader>
                <p
                    role="status"
                    className="rounded-md bg-muted px-3 py-2 text-sm"
                >
                    {message}
                </p>
                <div className="grid gap-1.5">
                    <Label htmlFor="approval-code">6-digit code</Label>
                    <Input
                        id="approval-code"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        maxLength={6}
                        value={code}
                        onChange={(e) =>
                            setCode(e.target.value.replace(/\D/g, ''))
                        }
                        onKeyDown={(e) => e.key === 'Enter' && submit()}
                        className="font-mono text-lg tracking-[0.3em]"
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        disabled={busy || code.length !== 6}
                        onClick={submit}
                    >
                        {busy ? 'Checking…' : 'Approve and continue'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

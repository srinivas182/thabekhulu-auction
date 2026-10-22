import Link from '@tiptap/extension-link';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Heading2,
    Heading3,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    value: string | null;
    onChange: (html: string) => void;
    label: string;
};

/**
 * Rich text for descriptions and terms (spec D4). Only basic formatting;
 * the server cleans the HTML again before saving.
 */
export default function RichTextEditor({ id, value, onChange, label }: Props) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                codeBlock: false,
                code: false,
                horizontalRule: false,
            }),
            Link.configure({
                openOnClick: false,
                autolink: true,
                protocols: ['https', 'mailto', 'tel'],
            }),
        ],
        content: value ?? '',
        immediatelyRender: false,
        editorProps: {
            attributes: {
                id,
                'aria-label': label,
                role: 'textbox',
                'aria-multiline': 'true',
                class: 'prose-sm min-h-40 max-w-none px-3 py-2 focus:outline-none [&_h2]:font-display [&_h2]:text-xl [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5 [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:pl-3',
            },
        },
        onUpdate: ({ editor: e }) => onChange(e.isEmpty ? '' : e.getHTML()),
    });

    const tool = (
        name: string,
        icon: ReactNode,
        active: boolean,
        run: () => void,
    ) => (
        <button
            type="button"
            aria-label={name}
            aria-pressed={active}
            title={name}
            onClick={run}
            className={cn(
                'inline-flex size-9 items-center justify-center rounded-md hover:bg-accent',
                active && 'bg-accent text-accent-foreground',
            )}
        >
            {icon}
        </button>
    );

    return (
        <div className="rounded-md border border-input bg-background focus-within:ring-2 focus-within:ring-ring">
            <div
                role="toolbar"
                aria-label={`${label} formatting`}
                className="flex flex-wrap gap-0.5 border-b border-border p-1"
            >
                {editor && (
                    <>
                        {tool(
                            'Bold',
                            <Bold className="size-4" />,
                            editor.isActive('bold'),
                            () => editor.chain().focus().toggleBold().run(),
                        )}
                        {tool(
                            'Italic',
                            <Italic className="size-4" />,
                            editor.isActive('italic'),
                            () => editor.chain().focus().toggleItalic().run(),
                        )}
                        {tool(
                            'Heading',
                            <Heading2 className="size-4" />,
                            editor.isActive('heading', { level: 2 }),
                            () =>
                                editor
                                    .chain()
                                    .focus()
                                    .toggleHeading({ level: 2 })
                                    .run(),
                        )}
                        {tool(
                            'Subheading',
                            <Heading3 className="size-4" />,
                            editor.isActive('heading', { level: 3 }),
                            () =>
                                editor
                                    .chain()
                                    .focus()
                                    .toggleHeading({ level: 3 })
                                    .run(),
                        )}
                        {tool(
                            'Bulleted list',
                            <List className="size-4" />,
                            editor.isActive('bulletList'),
                            () =>
                                editor.chain().focus().toggleBulletList().run(),
                        )}
                        {tool(
                            'Numbered list',
                            <ListOrdered className="size-4" />,
                            editor.isActive('orderedList'),
                            () =>
                                editor
                                    .chain()
                                    .focus()
                                    .toggleOrderedList()
                                    .run(),
                        )}
                        {tool(
                            'Quote',
                            <Quote className="size-4" />,
                            editor.isActive('blockquote'),
                            () =>
                                editor.chain().focus().toggleBlockquote().run(),
                        )}
                        {tool(
                            'Link',
                            <Link2 className="size-4" />,
                            editor.isActive('link'),
                            () => {
                                const previous = editor.getAttributes('link')
                                    .href as string | undefined;
                                const url = window.prompt(
                                    'Link address (https://…)',
                                    previous ?? 'https://',
                                );
                                if (url === null) return;
                                if (url === '')
                                    editor.chain().focus().unsetLink().run();
                                else
                                    editor
                                        .chain()
                                        .focus()
                                        .extendMarkRange('link')
                                        .setLink({ href: url })
                                        .run();
                            },
                        )}
                    </>
                )}
            </div>
            <EditorContent editor={editor} />
        </div>
    );
}

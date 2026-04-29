"use client";

import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";
import rehypeSlug from "rehype-slug";
import rehypeAutolinkHeadings from "rehype-autolink-headings";
import rehypeHighlight from "rehype-highlight";
import "highlight.js/styles/github.css";
import { cn } from "@/lib/utils";

/**
 * Renderizador de markdown coherente con el tema Sinapsa: sobrio, sin colores
 * pastel del prose default. Soporta GFM (tablas, task lists, strikethrough),
 * anchors automáticos en headings y syntax highlight en bloques de código.
 */
export function MarkdownView({
  source,
  className,
}: {
  source: string;
  className?: string;
}) {
  return (
    <div className={cn("markdown-body max-w-3xl text-[15px] leading-relaxed", className)}>
      <ReactMarkdown
        remarkPlugins={[remarkGfm]}
        rehypePlugins={[
          rehypeSlug,
          [rehypeAutolinkHeadings, { behavior: "wrap" }],
          rehypeHighlight,
        ]}
        components={{
          h1: ({ children, ...props }) => (
            <h1
              {...props}
              className="mt-0 scroll-m-20 text-3xl font-semibold tracking-tight"
            >
              {children}
            </h1>
          ),
          h2: ({ children, ...props }) => (
            <h2
              {...props}
              className="mt-12 scroll-m-20 border-b border-border pb-2 text-2xl font-semibold tracking-tight"
            >
              {children}
            </h2>
          ),
          h3: ({ children, ...props }) => (
            <h3
              {...props}
              className="mt-8 scroll-m-20 text-lg font-semibold tracking-tight"
            >
              {children}
            </h3>
          ),
          h4: ({ children, ...props }) => (
            <h4
              {...props}
              className="mt-6 scroll-m-20 text-base font-semibold tracking-tight text-muted-foreground"
            >
              {children}
            </h4>
          ),
          p: ({ children }) => <p className="mt-4 text-foreground/90">{children}</p>,
          a: ({ children, href }) => (
            <a
              href={href}
              target={href?.startsWith("http") ? "_blank" : undefined}
              rel={href?.startsWith("http") ? "noopener noreferrer" : undefined}
              className="font-medium text-foreground underline decoration-foreground/30 underline-offset-4 hover:decoration-foreground"
            >
              {children}
            </a>
          ),
          ul: ({ children }) => (
            <ul className="mt-4 list-disc space-y-1.5 pl-6 marker:text-muted-foreground">
              {children}
            </ul>
          ),
          ol: ({ children }) => (
            <ol className="mt-4 list-decimal space-y-1.5 pl-6 marker:text-muted-foreground">
              {children}
            </ol>
          ),
          li: ({ children }) => <li className="text-foreground/90">{children}</li>,
          blockquote: ({ children }) => (
            <blockquote className="mt-4 border-l-2 border-foreground/40 pl-4 text-muted-foreground italic">
              {children}
            </blockquote>
          ),
          code: ({ children, className, ...props }) => {
            const isInline = !className;
            if (isInline) {
              return (
                <code
                  {...props}
                  className="rounded-md border border-border bg-muted px-1.5 py-0.5 font-mono text-[0.85em]"
                >
                  {children}
                </code>
              );
            }
            return (
              <code className={className} {...props}>
                {children}
              </code>
            );
          },
          pre: ({ children }) => (
            <pre className="mt-4 overflow-x-auto rounded-2xl border border-border bg-muted/50 p-4 text-xs leading-relaxed">
              {children}
            </pre>
          ),
          table: ({ children }) => (
            <div className="mt-4 overflow-x-auto rounded-2xl border border-border">
              <table className="w-full border-collapse text-sm">{children}</table>
            </div>
          ),
          thead: ({ children }) => <thead className="bg-muted/60">{children}</thead>,
          tr: ({ children }) => (
            <tr className="border-b border-border last:border-0">{children}</tr>
          ),
          th: ({ children }) => (
            <th className="px-3 py-2 text-left font-semibold">{children}</th>
          ),
          td: ({ children }) => <td className="px-3 py-2 align-top">{children}</td>,
          hr: () => <hr className="my-10 border-border" />,
          strong: ({ children }) => <strong className="font-semibold">{children}</strong>,
          input: ({ type, checked, disabled, ...props }) => {
            // Task lists de GFM
            if (type === "checkbox") {
              return (
                <input
                  type="checkbox"
                  checked={checked}
                  disabled={disabled}
                  readOnly
                  className="mr-2 -mt-0.5 align-middle accent-foreground"
                  {...props}
                />
              );
            }
            return <input type={type} {...props} />;
          },
        }}
      >
        {source}
      </ReactMarkdown>
    </div>
  );
}

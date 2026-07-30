import { useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { chatService } from '../api/chatApi';
import { normalizePaginationMeta, parsePage } from '../../../shared/utils/pagination';

const PER_PAGE = 10;

export function useConversationsPagination() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [conversations, setConversations] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [page, setPage] = useState(parsePage(searchParams.get('page')));

  const fetchConversations = useCallback(async (nextPage = page) => {
    setLoading(true);

    try {
      const res = await chatService.getConversations({ page: nextPage, per_page: PER_PAGE });
      const payload = res.data;
      const items = Array.isArray(payload?.data) ? payload.data : [];

      setConversations(items);
      setMeta(normalizePaginationMeta(payload));
      setLoadError('');
      return items;
    } catch {
      setConversations([]);
      setMeta(null);
      setLoadError('Could not load conversations.');
      return [];
    } finally {
      setLoading(false);
    }
  }, [page]);

  useEffect(() => {
    fetchConversations(page);
  }, [fetchConversations, page]);

  const syncPageQuery = (nextPage) => {
    const next = new URLSearchParams(searchParams);

    if (nextPage > 1) {
      next.set('page', String(nextPage));
    } else {
      next.delete('page');
    }

    setSearchParams(next);
  };

  const goToPage = (nextPage) => {
    setPage(nextPage);
    syncPageQuery(nextPage);
  };

  const upsertConversation = (conversation) => {
    if (!conversation?.id) return;

    setConversations((prev) => {
      const exists = prev.some((item) => String(item.id) === String(conversation.id));

      if (!exists) return [conversation, ...prev].slice(0, PER_PAGE);

      const updated = prev.map((item) => (
        String(item.id) === String(conversation.id) ? { ...item, ...conversation } : item
      ));

      return [
        updated.find((item) => String(item.id) === String(conversation.id)),
        ...updated.filter((item) => String(item.id) !== String(conversation.id)),
      ].filter(Boolean);
    });
  };

  const patchConversation = (conversationId, patch) => {
    setConversations((prev) => prev.map((conversation) => (
      String(conversation.id) === String(conversationId)
        ? {
            ...conversation,
            ...(typeof patch === 'function' ? patch(conversation) : patch),
          }
        : conversation
    )));
  };

  return {
    conversations,
    setConversations,
    meta,
    loading,
    loadError,
    page,
    goToPage,
    upsertConversation,
    patchConversation,
    fetchConversations,
  };
}

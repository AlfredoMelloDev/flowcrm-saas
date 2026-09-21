import { beforeEach, describe, expect, it } from 'vitest'
import { handleResponseError } from './client'
import { queryClient } from './queryClient'

describe('handleResponseError', () => {
  beforeEach(() => {
    queryClient.clear()
  })

  it('clears the cached auth user on a 401 response', async () => {
    queryClient.setQueryData(['auth', 'me'], { id: '1', name: 'Alice' })

    await expect(
      handleResponseError({ response: { status: 401 } }),
    ).rejects.toBeTruthy()

    expect(queryClient.getQueryData(['auth', 'me'])).toBeNull()
  })

  it('leaves the cached auth user untouched on other errors', async () => {
    const user = { id: '1', name: 'Alice' }
    queryClient.setQueryData(['auth', 'me'], user)

    await expect(
      handleResponseError({ response: { status: 422 } }),
    ).rejects.toBeTruthy()

    expect(queryClient.getQueryData(['auth', 'me'])).toEqual(user)
  })

  it('does not throw or loop when there is no auth cache yet (e.g. the initial /me 401)', async () => {
    await expect(
      handleResponseError({ response: { status: 401 } }),
    ).rejects.toBeTruthy()

    expect(queryClient.getQueryData(['auth', 'me'])).toBeNull()
  })
})
